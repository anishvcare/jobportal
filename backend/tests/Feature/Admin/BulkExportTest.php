<?php

use App\Enums\DocumentType;
use App\Jobs\BuildBulkPackZip;
use App\Models\BulkExport;
use App\Models\CandidateProfile;
use App\Models\Document;
use App\Models\DownloadAudit;
use App\Models\JobCategory;
use App\Models\User;
use App\Services\Pdf\CandidatePackBuilder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessStartFailedException;
use Symfony\Component\Process\Process;

beforeEach(function () {
    Storage::fake('documents');
    // Profile/document observers dispatch BuildCandidatePack; fake the queue so
    // fixture setup does not run real builds and we control the export job.
    Queue::fake();
});

/**
 * Whether the qpdf binary is available (bulk ZIP needs it to build Profile.pdf).
 * Uniquely named to avoid clashing with helpers in other test files loaded in
 * the same Pest process.
 */
function bulkExportQpdfAvailable(): bool
{
    try {
        $process = new Process([(string) config('nexus.qpdf_path', 'qpdf'), '--version']);
        $process->run();

        return $process->isSuccessful();
    } catch (ProcessStartFailedException) {
        return false;
    }
}

/**
 * Store a document's bytes on the faked private disk and create its row.
 */
function bulkExportStoreDocument(
    CandidateProfile $profile,
    DocumentType $type,
    string $bytes,
    string $mime,
    string $extension
): Document {
    $path = "documents/{$profile->id}/{$type->value}/".Str::uuid()->toString().'.'.$extension;
    Storage::disk('documents')->put($path, $bytes);

    return Document::query()->create([
        'candidate_profile_id' => $profile->id,
        'type' => $type,
        'disk' => 'documents',
        'path' => $path,
        'original_name' => $type->value.'.'.$extension,
        'mime' => $mime,
        'size' => strlen($bytes),
        'page_count' => null,
        'sort_order' => 0,
        'sha256' => hash('sha256', $bytes),
    ]);
}

/**
 * Open a ZIP stored on the faked disk and return the ZipArchive plus the temp
 * path (caller closes the archive).
 *
 * @return array{0:ZipArchive,1:string}
 */
function bulkExportOpenZip(BulkExport $export): array
{
    $tmp = tempnam(sys_get_temp_dir(), 'bulkzip').'.zip';
    file_put_contents($tmp, Storage::disk($export->disk)->get($export->path));

    $zip = new ZipArchive;
    $zip->open($tmp);

    return [$zip, $tmp];
}

/**
 * The full list of entry names inside a ZIP.
 *
 * @return list<string>
 */
function bulkExportZipEntries(ZipArchive $zip): array
{
    $entries = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $entries[] = $zip->getNameIndex($i);
    }

    return $entries;
}

it('rejects guests, candidates and employers on export endpoints', function () {
    $admin = User::factory()->admin()->create();
    $export = BulkExport::factory()->create(['requested_by' => $admin->id]);

    // Guests: 401.
    $this->postJson('/api/admin/candidate-exports', ['candidate_ids' => [1]])->assertUnauthorized();
    $this->getJson("/api/admin/candidate-exports/{$export->id}")->assertUnauthorized();
    $this->getJson("/api/admin/candidate-exports/{$export->id}/download")->assertUnauthorized();

    foreach (['candidate', 'employer'] as $role) {
        $user = User::factory()->{$role}()->create();

        actingAsUser($user)->postJson('/api/admin/candidate-exports', ['candidate_ids' => [1]])->assertForbidden();
        actingAsUser($user)->getJson("/api/admin/candidate-exports/{$export->id}")->assertForbidden();
        actingAsUser($user)->getJson("/api/admin/candidate-exports/{$export->id}/download")->assertForbidden();
    }
});

it('rejects more than the configured candidate cap with 422', function () {
    $admin = User::factory()->admin()->create();
    $max = (int) config('nexus.bulk_export.max_candidates');

    $ids = range(1, $max + 1);

    actingAsUser($admin)
        ->postJson('/api/admin/candidate-exports', ['candidate_ids' => $ids])
        ->assertStatus(422)
        ->assertJsonValidationErrors('candidate_ids');
});

it('creates a queued export and dispatches the build job for up to the cap', function () {
    $admin = User::factory()->admin()->create();
    $a = createCandidateWithProfile();
    $b = createCandidateWithProfile();

    $response = actingAsUser($admin)->postJson('/api/admin/candidate-exports', [
        'candidate_ids' => [$a->id, $b->id],
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.status', BulkExport::STATUS_QUEUED)
        ->assertJsonPath('data.candidate_count', 2)
        ->assertJsonPath('data.ready', false);

    $export = BulkExport::query()->latest('id')->first();
    expect($export)->not->toBeNull()
        ->and($export->requested_by)->toBe($admin->id)
        ->and($export->candidate_ids)->toBe([$a->id, $b->id]);

    Queue::assertPushed(BuildBulkPackZip::class, fn (BuildBulkPackZip $job) => $job->bulkExportId === $export->id);
});

it('builds a ZIP with per-candidate pack, resume and uploaded CV plus a CSV manifest', function () {
    if (! bulkExportQpdfAvailable()) {
        $this->markTestSkipped('qpdf binary not available.');
    }

    $trade = JobCategory::query()->create([
        'parent_id' => null,
        'name' => 'Electrician',
        'slug' => 'electrician',
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $anita = createCandidateWithProfile(['full_name' => 'Anita Rao']);
    $anita->preferredCategories()->attach($trade->id);

    $ravi = CandidateProfile::factory()->withPassport()->create(['full_name' => 'Ravi Kumar']);
    $ravi->load('user');

    // Anita has an uploaded CV document.
    bulkExportStoreDocument($anita, DocumentType::Cv, '%PDF-1.4 uploaded cv', 'application/pdf', 'pdf');

    $export = BulkExport::factory()->create([
        'candidate_ids' => [$anita->id, $ravi->id],
        'status' => BulkExport::STATUS_QUEUED,
        'progress' => 0,
    ]);

    app(BuildBulkPackZip::class, ['bulkExportId' => $export->id])
        ->handle(app(CandidatePackBuilder::class));

    $export->refresh();

    expect($export->status)->toBe(BulkExport::STATUS_READY)
        ->and($export->progress)->toBe(100)
        ->and($export->expires_at)->not->toBeNull();

    // ~24h expiry (allow a minute of slack).
    expect($export->expires_at->diffInHours(now()->addHours(24), true))->toBeLessThan(1);

    [$zip, $tmp] = bulkExportOpenZip($export);
    $entries = bulkExportZipEntries($zip);

    // Per-candidate Profile.pdf + CV.pdf.
    $profilePdfs = array_filter($entries, fn ($e) => str_ends_with($e, '/Profile.pdf'));
    $cvPdfs = array_filter($entries, fn ($e) => str_ends_with($e, '/CV.pdf'));

    expect($profilePdfs)->toHaveCount(2)
        ->and($cvPdfs)->toHaveCount(2)
        ->and($entries)->toContain('candidates.csv');

    // Anita's uploaded CV is present.
    $uploaded = array_filter($entries, fn ($e) => str_contains($e, 'Uploaded-CV'));
    expect($uploaded)->toHaveCount(1);

    // The CSV lists names and the DECRYPTED passport number.
    $csv = $zip->getFromName('candidates.csv');
    expect($csv)->toContain('Anita Rao')
        ->and($csv)->toContain('Ravi Kumar')
        ->and($csv)->toContain('A1234567')
        ->and($csv)->toContain('Electrician');

    $zip->close();
    @unlink($tmp);
});

it('skips a candidate deleted mid-run without failing the job', function () {
    if (! bulkExportQpdfAvailable()) {
        $this->markTestSkipped('qpdf binary not available.');
    }

    $keep = createCandidateWithProfile(['full_name' => 'Kept Candidate']);
    $drop = createCandidateWithProfile(['full_name' => 'Gone Candidate']);

    $export = BulkExport::factory()->create([
        'candidate_ids' => [$keep->id, $drop->id],
        'status' => BulkExport::STATUS_QUEUED,
    ]);

    // Delete one candidate before the job runs.
    $drop->delete();

    app(BuildBulkPackZip::class, ['bulkExportId' => $export->id])
        ->handle(app(CandidatePackBuilder::class));

    $export->refresh();
    expect($export->status)->toBe(BulkExport::STATUS_READY);

    [$zip, $tmp] = bulkExportOpenZip($export);
    $entries = bulkExportZipEntries($zip);

    $profilePdfs = array_filter($entries, fn ($e) => str_ends_with($e, '/Profile.pdf'));
    expect($profilePdfs)->toHaveCount(1);

    $zip->close();
    @unlink($tmp);
});

it('streams the ZIP and writes a bulk_zip audit when ready', function () {
    $admin = User::factory()->admin()->create();

    Storage::disk('documents')->put('exports/1/candidates-export.zip', 'zip-bytes');

    $export = BulkExport::factory()->create([
        'requested_by' => $admin->id,
        'status' => BulkExport::STATUS_READY,
        'progress' => 100,
        'path' => 'exports/1/candidates-export.zip',
        'expires_at' => now()->addDay(),
    ]);

    $response = actingAsUser($admin)->get("/api/admin/candidate-exports/{$export->id}/download");

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/zip');

    $audit = DownloadAudit::query()->where('kind', DownloadAudit::KIND_BULK_ZIP)->first();
    expect($audit)->not->toBeNull()
        ->and($audit->bulk_export_id)->toBe($export->id)
        ->and($audit->candidate_profile_id)->toBeNull()
        ->and($audit->actor_user_id)->toBe($admin->id);
});

it('returns 410 when downloading an expired export', function () {
    $admin = User::factory()->admin()->create();

    Storage::disk('documents')->put('exports/2/candidates-export.zip', 'zip-bytes');

    $export = BulkExport::factory()->create([
        'requested_by' => $admin->id,
        'status' => BulkExport::STATUS_READY,
        'progress' => 100,
        'path' => 'exports/2/candidates-export.zip',
        'expires_at' => now()->subHour(),
    ]);

    actingAsUser($admin)
        ->get("/api/admin/candidate-exports/{$export->id}/download")
        ->assertStatus(410);
});

it('shows status and progress for polling', function () {
    $admin = User::factory()->admin()->create();
    $export = BulkExport::factory()->create([
        'requested_by' => $admin->id,
        'candidate_ids' => [1, 2, 3],
        'status' => BulkExport::STATUS_PROCESSING,
        'progress' => 42,
    ]);

    actingAsUser($admin)
        ->getJson("/api/admin/candidate-exports/{$export->id}")
        ->assertOk()
        ->assertJsonPath('data.status', BulkExport::STATUS_PROCESSING)
        ->assertJsonPath('data.progress', 42)
        ->assertJsonPath('data.candidate_count', 3)
        ->assertJsonPath('data.ready', false)
        ->assertJsonPath('data.download_url', null);
});

it('purges expired exports: deletes the file and the row', function () {
    $admin = User::factory()->admin()->create();

    Storage::disk('documents')->put('exports/9/candidates-export.zip', 'zip-bytes');

    $export = BulkExport::factory()->create([
        'requested_by' => $admin->id,
        'status' => BulkExport::STATUS_READY,
        'path' => 'exports/9/candidates-export.zip',
        'expires_at' => now()->subHours(2),
    ]);

    Artisan::call('exports:purge-expired');

    expect(Storage::disk('documents')->exists('exports/9/candidates-export.zip'))->toBeFalse();
    expect(BulkExport::query()->find($export->id))->toBeNull();
});

it('leaves unexpired exports in place when purging', function () {
    $admin = User::factory()->admin()->create();

    Storage::disk('documents')->put('exports/10/candidates-export.zip', 'zip-bytes');

    $export = BulkExport::factory()->create([
        'requested_by' => $admin->id,
        'status' => BulkExport::STATUS_READY,
        'path' => 'exports/10/candidates-export.zip',
        'expires_at' => now()->addDay(),
    ]);

    Artisan::call('exports:purge-expired');

    expect(Storage::disk('documents')->exists('exports/10/candidates-export.zip'))->toBeTrue();
    expect(BulkExport::query()->find($export->id))->not->toBeNull();
});
