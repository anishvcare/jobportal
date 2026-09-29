<?php

use App\Enums\DocumentType;
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

it('forbids employers from deleting a candidate account', function () {
    actingAsUser(User::factory()->employer()->create())->deleteJson('/api/candidate/account')
        ->assertForbidden();
});

it('returns 401 for guests deleting an account', function () {
    $this->deleteJson('/api/candidate/account')->assertUnauthorized();
});
