<?php

use App\Enums\DocumentType;
use App\Models\CandidatePack;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

it('hard-deletes the user, profile and physical document files', function () {
    Storage::fake('documents');

    $profile = createCandidateWithProfile();
    $user = $profile->user;

    $path = UploadedFile::fake()->create('aadhaar.pdf', 100)->store('candidate', 'documents');
    $document = $profile->documents()->create([
        'type' => DocumentType::AadhaarFront,
        'disk' => 'documents',
        'path' => $path,
        'original_name' => 'aadhaar.pdf',
        'mime' => 'application/pdf',
        'size' => 100,
    ]);

    Storage::disk('documents')->assertExists($path);

    actingAsUser($user)->deleteJson('/api/candidate/account')->assertNoContent();

    Storage::disk('documents')->assertMissing($path);
    $this->assertDatabaseMissing('users', ['id' => $user->id]);
    $this->assertDatabaseMissing('candidate_profiles', ['id' => $profile->id]);
    $this->assertDatabaseMissing('documents', ['id' => $document->id]);
});

it('removes the cached candidate pack artifact on account delete', function () {
    Storage::fake('documents');

    $profile = createCandidateWithProfile();
    $user = $profile->user;

    // Simulate a previously-generated, cached pack on the private disk plus its
    // candidate_packs row (the pack embeds photo, Aadhaar, SSLC and passport
    // pages, so it must not survive a hard delete).
    $packPath = "packs/{$profile->id}/candidate-pack.pdf";
    Storage::disk('documents')->put($packPath, '%PDF-1.4 fake pack');
    $profile->candidatePack()->updateOrCreate(
        ['candidate_profile_id' => $profile->id],
        [
            'disk' => 'documents',
            'path' => $packPath,
            'fingerprint' => str_repeat('a', 64),
            'status' => CandidatePack::STATUS_READY,
            'generated_at' => now(),
        ]
    );

    Storage::disk('documents')->assertExists($packPath);

    actingAsUser($user)->deleteJson('/api/candidate/account')->assertNoContent();

    Storage::disk('documents')->assertMissing($packPath);
    expect(Storage::disk('documents')->exists("packs/{$profile->id}"))->toBeFalse();
    $this->assertDatabaseMissing('candidate_packs', ['candidate_profile_id' => $profile->id]);
    $this->assertDatabaseMissing('candidate_profiles', ['id' => $profile->id]);
});

it('forbids employers from deleting a candidate account', function () {
    actingAsUser(User::factory()->employer()->create())->deleteJson('/api/candidate/account')
        ->assertForbidden();
});

it('returns 401 for guests deleting an account', function () {
    $this->deleteJson('/api/candidate/account')->assertUnauthorized();
});
