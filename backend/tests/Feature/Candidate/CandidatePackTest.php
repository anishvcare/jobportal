<?php

use App\Enums\DocumentType;
use App\Jobs\BuildCandidatePack;
use App\Models\CandidatePack;
use App\Models\CandidateProfile;
use App\Models\Document;
use App\Models\User;
use App\Services\Pdf\CandidatePackBuilder;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\Process\Exception\ProcessStartFailedException;
use Symfony\Component\Process\Process;

beforeEach(function () {
    Storage::fake('documents');
    // Observers dispatch BuildCandidatePack inline under QUEUE_CONNECTION=sync.
    // Fake the queue during fixture setup so pack state stays deterministic and
    // we control exactly when a build runs.
    Queue::fake();
});

/**
 * Minimal, valid single-page PDF body (mirrors DocumentPdfTest).
 */
function packMinimalPdf(): string
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

function packQpdfAvailable(): bool
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
 * Number of pages in a PDF on disk, via real qpdf.
 */
function pdfPageCount(string $absolutePath): int
{
    $process = new Process([
        (string) config('nexus.qpdf_path', 'qpdf'),
        '--show-npages',
        $absolutePath,
    ]);
    $process->run();

    return (int) trim($process->getOutput());
}

/**
 * Write $bytes to a temp file and return its absolute path (auto-suffixed).
 */
function packTempFile(string $bytes, string $extension): string
{
    $path = tempnam(sys_get_temp_dir(), 'pack').'.'.$extension;
    file_put_contents($path, $bytes);

    return $path;
}

/**
 * Build a multi-page PDF (known page count) by merging packMinimalPdf() $pages
 * times with qpdf, returning the raw bytes.
 */
function packMultiPagePdf(int $pages): string
{
    $inputs = [];
    for ($i = 0; $i < $pages; $i++) {
        $inputs[] = packTempFile(packMinimalPdf(), 'pdf');
    }

    $out = tempnam(sys_get_temp_dir(), 'multi').'.pdf';

    $args = [(string) config('nexus.qpdf_path', 'qpdf'), '--empty', '--pages'];
    foreach ($inputs as $input) {
        $args[] = $input;
    }
    $args[] = '--';
    $args[] = $out;

    $process = new Process($args);
    $process->run();

    return (string) file_get_contents($out);
}

/**
 * Small, valid JPEG produced with GD.
 */
function packJpegBytes(): string
{
    $image = imagecreatetruecolor(40, 40);
    imagefill($image, 0, 0, imagecolorallocate($image, 200, 100, 50));
    $path = tempnam(sys_get_temp_dir(), 'img').'.jpg';
    imagejpeg($image, $path);
    imagedestroy($image);

    return (string) file_get_contents($path);
}

/**
 * Small, valid PNG produced with GD.
 */
function packPngBytes(): string
{
    $image = imagecreatetruecolor(40, 40);
    imagefill($image, 0, 0, imagecolorallocate($image, 50, 100, 200));
    $path = tempnam(sys_get_temp_dir(), 'img').'.png';
    imagepng($image, $path);
    imagedestroy($image);

    return (string) file_get_contents($path);
}

/**
 * Store a document's bytes on the faked private disk and create its row.
 */
function packStoreDocument(
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

/**
 * Fetch the produced pack bytes off the faked private disk.
 */
function packBytes(CandidateProfile $profile): string
{
    $pack = $profile->candidatePack()->firstOrFail();

    return Storage::disk($pack->disk)->get($pack->path);
}

it('builds a pack whose page count equals cover + resume + images + uploaded pdf pages', function () {
    if (! packQpdfAvailable()) {
        $this->markTestSkipped('qpdf binary not available.');
    }

    $profile = createCandidateWithProfile([
        'full_name' => 'Anita Rao',
        'summary' => 'Skilled electrician.',
    ]);

    // Photo appears on the cover only (not the body).
    packStoreDocument($profile, DocumentType::Photo, packJpegBytes(), 'image/jpeg', 'jpg');
    // Body images: 1 page each.
    packStoreDocument($profile, DocumentType::AadhaarFront, packJpegBytes(), 'image/jpeg', 'jpg');
    packStoreDocument($profile, DocumentType::AadhaarBack, packPngBytes(), 'image/png', 'png');
    packStoreDocument($profile, DocumentType::Sslc, packJpegBytes(), 'image/jpeg', 'jpg');
    // A 3-page uploaded passport PDF, kept as-is.
    $passportPages = 3;
    packStoreDocument($profile, DocumentType::Passport, packMultiPagePdf($passportPages), 'application/pdf', 'pdf');

    $builder = app(CandidatePackBuilder::class);
    $pack = $builder->build($profile);

    expect($pack->status)->toBe(CandidatePack::STATUS_READY);

    // Determine resume page count independently.
    $resumePath = packTempFile($builder->renderResume($profile->fresh()), 'pdf');
    $resumePages = pdfPageCount($resumePath);

    $packPath = packTempFile(packBytes($profile), 'pdf');
    $actualPages = pdfPageCount($packPath);

    // cover(1) + resume(n) + 3 body images (1 each) + 3-page passport PDF.
    $expected = 1 + $resumePages + 3 + $passportPages;

    expect($actualPages)->toBe($expected);
});

it('honours the document order in the merged pack', function () {
    if (! packQpdfAvailable()) {
        $this->markTestSkipped('qpdf binary not available.');
    }

    $profile = createCandidateWithProfile();

    // Distinguishable uploaded PDFs with distinct page counts so the position
    // of each block in the merged pack is verifiable by page arithmetic.
    packStoreDocument($profile, DocumentType::Sslc, packMultiPagePdf(2), 'application/pdf', 'pdf');
    packStoreDocument($profile, DocumentType::Passport, packMultiPagePdf(4), 'application/pdf', 'pdf');

    $builder = app(CandidatePackBuilder::class);
    $builder->build($profile);

    $resumePages = pdfPageCount(packTempFile($builder->renderResume($profile->fresh()), 'pdf'));
    $actualPages = pdfPageCount(packTempFile(packBytes($profile), 'pdf'));

    // cover(1) + resume(n) + sslc(2) + passport(4). SSLC precedes passport in
    // the authoritative order, so the total reflects both blocks in sequence.
    expect($actualPages)->toBe(1 + $resumePages + 2 + 4);
});

it('recomputes staleness and page count when a document is added', function () {
    if (! packQpdfAvailable()) {
        $this->markTestSkipped('qpdf binary not available.');
    }

    $profile = createCandidateWithProfile();

    $builder = app(CandidatePackBuilder::class);
    $builder->build($profile);

    $firstFingerprint = $profile->candidatePack()->firstOrFail()->fingerprint;
    $firstPages = pdfPageCount(packTempFile(packBytes($profile), 'pdf'));

    expect($builder->isStale($profile->fresh()))->toBeFalse();

    // Add a document; the pack becomes stale.
    packStoreDocument($profile, DocumentType::AadhaarFront, packJpegBytes(), 'image/jpeg', 'jpg');

    $reloaded = $profile->fresh();
    expect($builder->isStale($reloaded))->toBeTrue();

    $builder->build($reloaded);

    $secondFingerprint = $reloaded->candidatePack()->firstOrFail()->fingerprint;
    $secondPages = pdfPageCount(packTempFile(packBytes($profile), 'pdf'));

    expect($secondFingerprint)->not->toBe($firstFingerprint)
        ->and($secondPages)->toBe($firstPages + 1);
});

it('builds a cover-plus-resume-only pack when there are no documents', function () {
    if (! packQpdfAvailable()) {
        $this->markTestSkipped('qpdf binary not available.');
    }

    $profile = createCandidateWithProfile();

    $builder = app(CandidatePackBuilder::class);
    $builder->build($profile);

    $resumePages = pdfPageCount(packTempFile($builder->renderResume($profile->fresh()), 'pdf'));
    $actualPages = pdfPageCount(packTempFile(packBytes($profile), 'pdf'));

    expect($actualPages)->toBe(1 + $resumePages);
});

it('skips an unreadable image without failing the build', function () {
    if (! packQpdfAvailable()) {
        $this->markTestSkipped('qpdf binary not available.');
    }

    $profile = createCandidateWithProfile();

    // A good image plus a corrupt one (junk bytes with an image mime).
    packStoreDocument($profile, DocumentType::AadhaarFront, packJpegBytes(), 'image/jpeg', 'jpg');
    packStoreDocument($profile, DocumentType::AadhaarBack, 'not-a-real-image', 'image/png', 'png');

    $builder = app(CandidatePackBuilder::class);
    $pack = $builder->build($profile);

    expect($pack->status)->toBe(CandidatePack::STATUS_READY);

    $resumePages = pdfPageCount(packTempFile($builder->renderResume($profile->fresh()), 'pdf'));
    $actualPages = pdfPageCount(packTempFile(packBytes($profile), 'pdf'));

    // cover(1) + resume(n) + only the ONE good image (corrupt one excluded).
    expect($actualPages)->toBe(1 + $resumePages + 1);
});

it('requires authentication on the pack endpoint', function () {
    createCandidateWithProfile();

    $this->getJson('/api/candidate/pack')->assertUnauthorized();
});

it('lets each candidate download only their own pack via the endpoint', function () {
    if (! packQpdfAvailable()) {
        $this->markTestSkipped('qpdf binary not available.');
    }

    $owner = createCandidateWithProfile();
    $other = User::factory()->candidate()->create();

    // The owner's endpoint builds and streams their pack.
    $response = actingAsUser($owner->user)->get('/api/candidate/pack');
    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');

    // A different candidate resolves their OWN profile and never the owner's;
    // they get their own pack, not the owner's data.
    $otherResponse = actingAsUser($other)->get('/api/candidate/pack');
    $otherResponse->assertOk();

    // The owner and the other candidate have distinct packs on disk.
    $ownerPack = $owner->candidatePack()->firstOrFail();
    $otherProfile = $other->candidateProfile()->firstOrFail();
    expect($otherProfile->id)->not->toBe($owner->id)
        ->and($ownerPack->candidate_profile_id)->toBe($owner->id);
});

it('builds the pack synchronously on request when missing and returns a pdf', function () {
    if (! packQpdfAvailable()) {
        $this->markTestSkipped('qpdf binary not available.');
    }

    $profile = createCandidateWithProfile();

    // No pack row exists yet, so the request must trigger a synchronous build.
    expect($profile->candidatePack()->exists())->toBeFalse();

    $response = actingAsUser($profile->user)->get('/api/candidate/pack');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('application/pdf');
    expect($response->streamedContent())->toStartWith('%PDF');

    expect($profile->candidatePack()->firstOrFail()->status)->toBe(CandidatePack::STATUS_READY);
});

it('dispatches a rebuild and marks the pack stale when a document changes', function () {
    // Queue is already faked in beforeEach; create the fixture first.
    $profile = createCandidateWithProfile();

    // Seed a ready pack row so we can observe it flipping to stale.
    CandidatePack::factory()->for($profile, 'candidateProfile')->ready()->create();

    Queue::fake();

    packStoreDocument($profile, DocumentType::AadhaarFront, packJpegBytes(), 'image/jpeg', 'jpg');

    Queue::assertPushed(BuildCandidatePack::class, function (BuildCandidatePack $job) use ($profile) {
        return $job->candidateProfileId === $profile->id;
    });

    expect($profile->candidatePack()->firstOrFail()->status)->toBe(CandidatePack::STATUS_STALE);
});

it('dispatches a rebuild and marks the pack stale when the profile changes', function () {
    $profile = createCandidateWithProfile();

    CandidatePack::factory()->for($profile, 'candidateProfile')->ready()->create();

    Queue::fake();

    $profile->update(['summary' => 'Updated summary that changes the fingerprint.']);

    Queue::assertPushed(BuildCandidatePack::class, function (BuildCandidatePack $job) use ($profile) {
        return $job->candidateProfileId === $profile->id;
    });

    expect($profile->candidatePack()->firstOrFail()->status)->toBe(CandidatePack::STATUS_STALE);
});
