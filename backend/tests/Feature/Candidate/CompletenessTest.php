<?php

use App\Models\User;
use App\Services\Documents\PdfInspector;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('documents');
});

function uploadDoc(User $user, string $type): void
{
    actingAsUser($user)->postJson('/api/candidate/documents', [
        'type' => $type,
        'file' => UploadedFile::fake()->image("{$type}.jpg"),
    ])->assertCreated();
}

it('reports 0% and lists all required docs for a fresh profile', function () {
    $user = User::factory()->candidate()->create();

    actingAsUser($user)->getJson('/api/candidate/profile/completeness')
        ->assertOk()
        ->assertJsonPath('data.percentage', 0)
        ->assertJsonPath('data.pack_ready', false)
        ->assertJson(fn ($json) => $json->where('data.missing', ['photo', 'aadhaar_front', 'aadhaar_back', 'sslc'])->etc());
});

it('reports 100% once the four required docs are uploaded', function () {
    $user = User::factory()->candidate()->create();

    uploadDoc($user, 'photo');
    uploadDoc($user, 'aadhaar_front');
    uploadDoc($user, 'aadhaar_back');
    uploadDoc($user, 'sslc');

    actingAsUser($user)->getJson('/api/candidate/profile/completeness')
        ->assertOk()
        ->assertJsonPath('data.percentage', 100)
        ->assertJsonPath('data.missing', [])
        ->assertJsonPath('data.pack_ready', true);
});

it('requires a passport document only when has_passport is true', function () {
    $user = User::factory()->candidate()->create();

    actingAsUser($user)->patchJson('/api/candidate/profile', ['has_passport' => true])->assertOk();

    uploadDoc($user, 'photo');
    uploadDoc($user, 'aadhaar_front');
    uploadDoc($user, 'aadhaar_back');
    uploadDoc($user, 'sslc');

    actingAsUser($user)->getJson('/api/candidate/profile/completeness')
        ->assertOk()
        ->assertJsonPath('data.missing', ['passport'])
        ->assertJson(fn ($json) => $json->where('data.required', ['photo', 'aadhaar_front', 'aadhaar_back', 'sslc', 'passport'])->etc());
});

it('sets pack_ready when a profile_pdf exists while individual docs remain missing', function () {
    // Upload validation now fails CLOSED on the encrypted-PDF probe: a PDF is
    // only accepted when qpdf confirms it is not encrypted. Fake PdfInspector so
    // this test is deterministic and does not depend on qpdf being installed.
    $this->mock(PdfInspector::class, function ($mock) {
        $mock->shouldReceive('isEncrypted')->andReturn(false);
        $mock->shouldReceive('pageCount')->andReturn(1);
    });

    $user = User::factory()->candidate()->create();

    actingAsUser($user)->postJson('/api/candidate/documents', [
        'type' => 'profile_pdf',
        'file' => UploadedFile::fake()->create('pack.pdf', 100, 'application/pdf'),
    ])->assertCreated();

    actingAsUser($user)->getJson('/api/candidate/profile/completeness')
        ->assertOk()
        ->assertJsonPath('data.has_profile_pdf', true)
        ->assertJsonPath('data.pack_ready', true)
        // Individual required docs are still surfaced as missing.
        ->assertJsonPath('data.missing', ['photo', 'aadhaar_front', 'aadhaar_back', 'sslc']);
});
