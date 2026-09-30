<?php

use App\Models\Document;
use App\Models\User;
use App\Services\Documents\PdfInspector;
use App\Services\Documents\UploadValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Exception\ProcessStartFailedException;
use Symfony\Component\Process\Process;

beforeEach(function () {
    Storage::fake('documents');
});

/**
 * Minimal, valid single-page PDF body.
 */
function minimalPdf(): string
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

function qpdfAvailable(): bool
{
    try {
        $process = new Process([(string) config('nexus.qpdf_path', 'qpdf'), '--version']);
        $process->run();

        return $process->isSuccessful();
    } catch (ProcessStartFailedException) {
        return false;
    }
}

it('records the page count for an uploaded pdf when qpdf is available', function () {
    if (! qpdfAvailable()) {
        $this->markTestSkipped('qpdf binary not available.');
    }

    $user = User::factory()->candidate()->create();

    $tmp = tempnam(sys_get_temp_dir(), 'pdf').'.pdf';
    file_put_contents($tmp, minimalPdf());
    $file = new UploadedFile($tmp, 'doc.pdf', 'application/pdf', null, true);

    actingAsUser($user)->postJson('/api/candidate/documents', [
        'type' => 'profile_pdf',
        'file' => $file,
    ])->assertCreated();

    $document = Document::firstOrFail();
    expect($document->page_count)->toBeGreaterThanOrEqual(1);
});

it('rejects a pdf when qpdf cannot verify encryption (fail closed)', function () {
    // Fake PdfInspector so isEncrypted() returns null (qpdf missing/unreadable
    // at runtime). The upload MUST be rejected rather than silently stored.
    $this->mock(PdfInspector::class, function ($mock) {
        $mock->shouldReceive('isEncrypted')->andReturn(null);
        $mock->shouldReceive('pageCount')->andReturn(null);
    });

    $user = User::factory()->candidate()->create();

    $tmp = tempnam(sys_get_temp_dir(), 'pdf').'.pdf';
    file_put_contents($tmp, minimalPdf());
    $file = new UploadedFile($tmp, 'doc.pdf', 'application/pdf', null, true);

    actingAsUser($user)->postJson('/api/candidate/documents', [
        'type' => 'profile_pdf',
        'file' => $file,
    ])
        ->assertUnprocessable()
        ->assertJsonFragment(['file' => [UploadValidator::UNVERIFIABLE_PDF_MESSAGE]]);

    expect(Document::count())->toBe(0);
});

it('accepts a pdf when qpdf confirms it is not encrypted', function () {
    // Fake PdfInspector so the happy path is deterministic without qpdf.
    $this->mock(PdfInspector::class, function ($mock) {
        $mock->shouldReceive('isEncrypted')->andReturn(false);
        $mock->shouldReceive('pageCount')->andReturn(1);
    });

    $user = User::factory()->candidate()->create();

    $tmp = tempnam(sys_get_temp_dir(), 'pdf').'.pdf';
    file_put_contents($tmp, minimalPdf());
    $file = new UploadedFile($tmp, 'doc.pdf', 'application/pdf', null, true);

    actingAsUser($user)->postJson('/api/candidate/documents', [
        'type' => 'profile_pdf',
        'file' => $file,
    ])->assertCreated();

    expect(Document::count())->toBe(1);
    expect(Document::firstOrFail()->page_count)->toBe(1);
});

it('rejects an encrypted pdf with the password-protected message', function () {
    if (! qpdfAvailable()) {
        $this->markTestSkipped('qpdf binary not available.');
    }

    $user = User::factory()->candidate()->create();

    $plain = tempnam(sys_get_temp_dir(), 'plain').'.pdf';
    file_put_contents($plain, minimalPdf());

    $encrypted = tempnam(sys_get_temp_dir(), 'enc').'.pdf';
    $encrypt = new Process([
        (string) config('nexus.qpdf_path', 'qpdf'),
        '--encrypt', 'userpass', 'ownerpass', '256', '--',
        $plain, $encrypted,
    ]);
    $encrypt->run();

    expect($encrypt->isSuccessful())->toBeTrue();

    $file = new UploadedFile($encrypted, 'secret.pdf', 'application/pdf', null, true);

    actingAsUser($user)->postJson('/api/candidate/documents', [
        'type' => 'profile_pdf',
        'file' => $file,
    ])
        ->assertUnprocessable()
        ->assertJsonFragment(['file' => [UploadValidator::ENCRYPTED_PDF_MESSAGE]]);

    expect(Document::count())->toBe(0);
});
