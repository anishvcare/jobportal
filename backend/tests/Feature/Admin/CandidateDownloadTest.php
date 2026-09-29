<?php

use App\Enums\DocumentType;
use App\Models\CandidatePack;
use App\Models\CandidateProfile;
use App\Models\Document;
use App\Models\DownloadAudit;
use App\Models\User;
use App\Services\Pdf\CandidatePackBuilder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessStartFailedException;
use Symfony\Component\Process\Process;

beforeEach(function () {
    Storage::fake('documents');
});

/**
 * Whether the qpdf merge backend is available in this environment (mirrors
 * tests/Feature/Candidate/CandidatePackTest.php). Resume/pack tests build real
 * PDFs and skip-with-message when it is absent. Named distinctly to avoid a
 * redeclare clash with the candidate pack test helpers (Pest loads all files).
 */
function adminDownloadQpdfAvailable(): bool
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
 * Minimal, valid single-page PDF body.
 */
function adminDownloadMinimalPdf(): string
{
    return "%PDF-1.4\n"
        ."1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj\n"
        ."2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj\n"
        ."3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]>>endobj\n"
        ."xref\n0 4\n"
        ."0000000000 65535 f \n"
        ."0000000009 00000 n \n"
        ."0000000052 00000 n \n"
        ."0000000101 00000 n \n"
        ."trailer<</Size 4/Root 1 0 R>>\n"
        ."startxref\n164\n"
        .'%%EOF';
}

/**
 * Store a document's bytes on the faked private disk and create its row.
 */
function adminDownloadStoreDocument(
    CandidateProfile $profile,
    DocumentType $type,
    string $bytes,
    string $mime,
    string $extension,
    int $sortOrder = 0
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
        'sort_order' => $sortOrder,
        'sha256' => hash('sha256', $bytes),
    ]);
}

/*
| Access control.
*/

it('rejects guests on every download endpoint', function () {
    $profile = createCandidateWithProfile();
    $document = adminDownloadStoreDocument($profile, DocumentType::Cv, adminDownloadMinimalPdf(), 'application/pdf', 'pdf');

    $this->getJson("/api/admin/candidates/{$profile->id}/documents/{$document->id}/download")->assertUnauthorized();
    $this->getJson("/api/admin/candidates/{$profile->id}/resume")->assertUnauthorized();
    $this->getJson("/api/admin/candidates/{$profile->id}/pack")->assertUnauthorized();
    $this->getJson('/api/admin/download-audits')->assertUnauthorized();
});

it('rejects candidates and employers on every download endpoint', function () {
    $profile = createCandidateWithProfile();
    $document = adminDownloadStoreDocument($profile, DocumentType::Cv, adminDownloadMinimalPdf(), 'application/pdf', 'pdf');

    foreach ([User::factory()->candidate()->create(), User::factory()->employer()->create()] as $user) {
        actingAsUser($user)->getJson("/api/admin/candidates/{$profile->id}/documents/{$document->id}/download")->assertForbidden();
        actingAsUser($user)->getJson("/api/admin/candidates/{$profile->id}/resume")->assertForbidden();
        actingAsUser($user)->getJson("/api/admin/candidates/{$profile->id}/pack")->assertForbidden();
        actingAsUser($user)->getJson('/api/admin/download-audits')->assertForbidden();
    }
});

/*
| Document download.
*/

it('streams a candidate document and writes a document audit row for an admin', function () {
    $admin = User::factory()->admin()->create();
    $profile = createCandidateWithProfile();
    $bytes = adminDownloadMinimalPdf();
    $document = adminDownloadStoreDocument($profile, DocumentType::Cv, $bytes, 'application/pdf', 'pdf');

    $response = actingAsUser($admin)->get("/api/admin/candidates/{$profile->id}/documents/{$document->id}/download");

    $response->assertOk();
    expect($response->streamedContent())->toBe($bytes);

    $audit = DownloadAudit::query()->firstOrFail();
    expect($audit->kind)->toBe(DownloadAudit::KIND_DOCUMENT)
        ->and($audit->actor_user_id)->toBe($admin->id)
        ->and($audit->candidate_profile_id)->toBe($profile->id)
        ->and($audit->document_id)->toBe($document->id);
});

it('returns 404 when the document belongs to another profile', function () {
    $admin = User::factory()->admin()->create();
    $profile = createCandidateWithProfile();
    $other = createCandidateWithProfile();
    $foreignDocument = adminDownloadStoreDocument($other, DocumentType::Cv, adminDownloadMinimalPdf(), 'application/pdf', 'pdf');

    actingAsUser($admin)
        ->getJson("/api/admin/candidates/{$profile->id}/documents/{$foreignDocument->id}/download")
        ->assertNotFound();

    expect(DownloadAudit::query()->count())->toBe(0);
});

/*
| Resume download.
*/

it('streams a generated resume pdf and writes a resume audit row', function () {
    if (! adminDownloadQpdfAvailable()) {
        $this->markTestSkipped('qpdf binary not available.');
    }

    $admin = User::factory()->admin()->create();
    $profile = createCandidateWithProfile(['full_name' => 'Anita Rao', 'summary' => 'Skilled electrician.']);

    $response = actingAsUser($admin)->get("/api/admin/candidates/{$profile->id}/resume");

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf')
        ->and($response->streamedContent())->toStartWith('%PDF');

    $audit = DownloadAudit::query()->firstOrFail();
    expect($audit->kind)->toBe(DownloadAudit::KIND_RESUME)
        ->and($audit->actor_user_id)->toBe($admin->id)
        ->and($audit->candidate_profile_id)->toBe($profile->id)
        ->and($audit->document_id)->toBeNull();
});

/*
| Pack download.
*/

it('ensure-fresh-builds the pack, streams it and writes a pack audit row', function () {
    if (! adminDownloadQpdfAvailable()) {
        $this->markTestSkipped('qpdf binary not available.');
    }

    $admin = User::factory()->admin()->create();
    $profile = createCandidateWithProfile();

    // Force a stale pack so the request must ensureFresh-rebuild it before
    // streaming (asserts the ensureFresh path, not just a cache hit).
    $profile->candidatePack()->update(['status' => CandidatePack::STATUS_STALE]);
    expect($profile->candidatePack()->firstOrFail()->status)->toBe(CandidatePack::STATUS_STALE);

    $response = actingAsUser($admin)->get("/api/admin/candidates/{$profile->id}/pack");

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');

    // The pack was ensureFresh'd into a READY row.
    expect($profile->candidatePack()->firstOrFail()->status)->toBe(CandidatePack::STATUS_READY);

    $audit = DownloadAudit::query()->firstOrFail();
    expect($audit->kind)->toBe(DownloadAudit::KIND_PACK)
        ->and($audit->actor_user_id)->toBe($admin->id)
        ->and($audit->candidate_profile_id)->toBe($profile->id);
});

it('returns 503 and writes no audit row when the pack build does not become ready', function () {
    $admin = User::factory()->admin()->create();
    $profile = createCandidateWithProfile();

    // Force ensureFresh to yield a non-READY pack (build failure) by binding a
    // fake builder. The auditor contract is audit-on-success-only, so the 503
    // branch must return before any download_audits row is written.
    $notReady = new CandidatePack([
        'candidate_profile_id' => $profile->id,
        'status' => CandidatePack::STATUS_FAILED,
        'disk' => 'documents',
        'path' => null,
    ]);

    $this->mock(CandidatePackBuilder::class, function ($mock) use ($notReady) {
        $mock->shouldReceive('ensureFresh')->once()->andReturn($notReady);
    });

    actingAsUser($admin)
        ->getJson("/api/admin/candidates/{$profile->id}/pack")
        ->assertStatus(503);

    expect(DownloadAudit::query()->count())->toBe(0);
});

/*
| Audit log listing.
*/

it('returns the download audit log paginated for an admin', function () {
    $admin = User::factory()->admin()->create();
    $profile = createCandidateWithProfile();

    DownloadAudit::factory()->count(3)->create([
        'actor_user_id' => $admin->id,
        'candidate_profile_id' => $profile->id,
    ]);

    $response = actingAsUser($admin)->getJson('/api/admin/download-audits');

    $response->assertOk()
        ->assertJsonPath('meta.total', 3)
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.candidate_name', $profile->full_name);
});

it('keeps audit rows after the candidate is hard-deleted with a null candidate id', function () {
    $admin = User::factory()->admin()->create();
    $profile = createCandidateWithProfile();

    $audit = DownloadAudit::factory()->create([
        'actor_user_id' => $admin->id,
        'candidate_profile_id' => $profile->id,
        'kind' => DownloadAudit::KIND_PACK,
    ]);

    // Mirror AccountController: hard-delete the user which cascades to the
    // profile; the audit row survives with a null candidate id (nullOnDelete).
    $profile->user->delete();

    $reloaded = $audit->fresh();
    expect($reloaded)->not->toBeNull()
        ->and($reloaded->candidate_profile_id)->toBeNull();

    // The listing exposes a null candidate_name for the deleted candidate.
    actingAsUser($admin)->getJson('/api/admin/download-audits')
        ->assertOk()
        ->assertJsonPath('meta.total', 1)
        ->assertJsonPath('data.0.candidate_name', null);
});
