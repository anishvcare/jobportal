<?php

use App\Enums\DocumentType;
use App\Models\CandidateProfile;
use App\Models\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

it('round-trips the passport number through the encrypted cast', function () {
    $profile = CandidateProfile::factory()->withPassport()->create();

    // Read back as plaintext through the cast.
    expect($profile->fresh()->passport_number)->toBe('A1234567');

    // Stored ciphertext must not equal the plaintext.
    $stored = DB::table('candidate_profiles')->where('id', $profile->id)->value('passport_number');
    expect($stored)->not->toBe('A1234567')->not->toBeNull();
});

it('exposes a private documents disk that is not symlinked to public', function () {
    expect(Storage::disk('documents'))->not->toBeNull();
    expect(config('filesystems.disks.documents.visibility'))->toBe('private');
    expect(array_values(config('filesystems.links')))
        ->not->toContain(storage_path('app/documents'));
});

it('links a candidate profile to its user and documents', function () {
    $profile = createCandidateWithProfile();

    expect($profile->user)->not->toBeNull();
    expect($profile->user->candidateProfile->is($profile))->toBeTrue();

    Document::create([
        'candidate_profile_id' => $profile->id,
        'type' => DocumentType::Photo,
        'path' => 'documents/photo.jpg',
        'original_name' => 'photo.jpg',
        'mime' => 'image/jpeg',
        'size' => 1234,
    ]);

    $doc = $profile->documents()->firstOrFail();
    expect($doc->type)->toBe(DocumentType::Photo);
    expect($doc->disk)->toBe('documents');
});

it('describes document type behaviour', function () {
    expect(DocumentType::allowsMultiple(DocumentType::Passport))->toBeTrue();
    expect(DocumentType::allowsMultiple(DocumentType::Photo))->toBeFalse();
    expect(DocumentType::requiredForCompleteness())->toBe([
        DocumentType::Photo,
        DocumentType::AadhaarFront,
        DocumentType::AadhaarBack,
        DocumentType::Sslc,
    ]);
    expect(DocumentType::maxSizeBytes(DocumentType::ProfilePdf))->toBe(50 * 1024 * 1024);
    expect(DocumentType::maxSizeBytes(DocumentType::Photo))->toBe(10 * 1024 * 1024);
});
