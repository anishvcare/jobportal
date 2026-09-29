<?php

use App\Models\Document;
use App\Models\User;
use App\Services\Documents\UploadValidator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('documents');
});

it('uploads a photo and returns a resource without the storage path', function () {
    $user = User::factory()->candidate()->create();

    $response = actingAsUser($user)->postJson('/api/candidate/documents', [
        'type' => 'photo',
        'file' => UploadedFile::fake()->image('photo.jpg'),
    ])
        ->assertCreated()
        ->assertJsonPath('data.type', 'photo')
        ->assertJsonPath('data.original_name', 'photo.jpg');

    $response->assertJsonMissingPath('data.path');
    $response->assertJsonMissingPath('data.disk');

    $document = Document::firstOrFail();
    expect($document->mime)->toBe('image/jpeg');
    Storage::disk('documents')->assertExists($document->path);
    $response->assertJsonPath('data.download_url', "/api/candidate/documents/{$document->id}/download");
});

it('replaces a single-value type on a second upload', function () {
    $user = User::factory()->candidate()->create();

    actingAsUser($user)->postJson('/api/candidate/documents', [
        'type' => 'photo',
        'file' => UploadedFile::fake()->image('first.jpg'),
    ])->assertCreated();

    $first = Document::firstOrFail();

    actingAsUser($user)->postJson('/api/candidate/documents', [
        'type' => 'photo',
        'file' => UploadedFile::fake()->image('second.jpg'),
    ])->assertCreated();

    expect(Document::where('type', 'photo')->count())->toBe(1);
    Storage::disk('documents')->assertMissing($first->path);
    $this->assertDatabaseMissing('documents', ['id' => $first->id]);
});

it('appends multiple files for a multi-value type', function () {
    $user = User::factory()->candidate()->create();

    actingAsUser($user)->postJson('/api/candidate/documents', [
        'type' => 'education_cert',
        'file' => UploadedFile::fake()->image('cert1.jpg'),
    ])->assertCreated();

    actingAsUser($user)->postJson('/api/candidate/documents', [
        'type' => 'education_cert',
        'file' => UploadedFile::fake()->image('cert2.jpg'),
    ])->assertCreated();

    expect(Document::where('type', 'education_cert')->count())->toBe(2);
});

it('rejects a file larger than 10MB for a non-pdf type', function () {
    $user = User::factory()->candidate()->create();

    actingAsUser($user)->postJson('/api/candidate/documents', [
        'type' => 'photo',
        // 11MB image.
        'file' => UploadedFile::fake()->create('big.jpg', 11 * 1024, 'image/jpeg'),
    ])->assertUnprocessable()->assertJsonValidationErrors('file');
});

it('rejects a pdf larger than 50MB for profile_pdf', function () {
    $user = User::factory()->candidate()->create();

    actingAsUser($user)->postJson('/api/candidate/documents', [
        'type' => 'profile_pdf',
        'file' => UploadedFile::fake()->create('huge.pdf', 51 * 1024, 'application/pdf'),
    ])->assertUnprocessable()->assertJsonValidationErrors('file');
});

it('rejects a HEIC image reaching the server with the retry message', function () {
    $user = User::factory()->candidate()->create();

    actingAsUser($user)->postJson('/api/candidate/documents', [
        'type' => 'photo',
        'file' => UploadedFile::fake()->create('photo.heic', 200, 'image/heic'),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('file')
        ->assertJsonFragment(['file' => [UploadValidator::HEIC_MESSAGE]]);
});

it('lets a candidate download only their own document', function () {
    $owner = User::factory()->candidate()->create();

    actingAsUser($owner)->postJson('/api/candidate/documents', [
        'type' => 'photo',
        'file' => UploadedFile::fake()->image('photo.jpg'),
    ])->assertCreated();

    $document = Document::firstOrFail();

    actingAsUser($owner)->get("/api/candidate/documents/{$document->id}/download")->assertOk();

    $intruder = User::factory()->candidate()->create();
    actingAsUser($intruder)->getJson("/api/candidate/documents/{$document->id}/download")->assertForbidden();
});

it('blocks employers and admins from candidate document endpoints', function () {
    actingAsUser(User::factory()->employer()->create())->getJson('/api/candidate/documents')->assertForbidden();
    actingAsUser(User::factory()->admin()->create())->getJson('/api/candidate/documents')->assertForbidden();
});

it('returns 401 for guests on document endpoints', function () {
    $this->getJson('/api/candidate/documents')->assertUnauthorized();
});

it('reorders passport documents by the given order', function () {
    $user = User::factory()->candidate()->create();

    actingAsUser($user)->postJson('/api/candidate/documents', [
        'type' => 'passport',
        'file' => UploadedFile::fake()->image('page1.jpg'),
    ])->assertCreated();

    actingAsUser($user)->postJson('/api/candidate/documents', [
        'type' => 'passport',
        'file' => UploadedFile::fake()->image('page2.jpg'),
    ])->assertCreated();

    $ids = Document::where('type', 'passport')->orderBy('id')->pluck('id')->all();
    $reversed = array_reverse($ids);

    actingAsUser($user)->patchJson('/api/candidate/documents/reorder', [
        'order' => $reversed,
    ])->assertOk();

    expect((int) Document::find($reversed[0])->sort_order)->toBe(0)
        ->and((int) Document::find($reversed[1])->sort_order)->toBe(1);
});

it('deletes a document file and row', function () {
    $user = User::factory()->candidate()->create();

    actingAsUser($user)->postJson('/api/candidate/documents', [
        'type' => 'photo',
        'file' => UploadedFile::fake()->image('photo.jpg'),
    ])->assertCreated();

    $document = Document::firstOrFail();

    actingAsUser($user)->deleteJson("/api/candidate/documents/{$document->id}")->assertNoContent();

    Storage::disk('documents')->assertMissing($document->path);
    $this->assertDatabaseMissing('documents', ['id' => $document->id]);
});

it('replaces the file behind a specific multi document', function () {
    $user = User::factory()->candidate()->create();

    actingAsUser($user)->postJson('/api/candidate/documents', [
        'type' => 'passport',
        'file' => UploadedFile::fake()->image('page1.jpg'),
    ])->assertCreated();

    $document = Document::firstOrFail();
    $oldPath = $document->path;

    actingAsUser($user)->post("/api/candidate/documents/{$document->id}", [
        'type' => 'passport',
        'file' => UploadedFile::fake()->image('page1-new.jpg'),
    ])->assertOk()->assertJsonPath('data.original_name', 'page1-new.jpg');

    Storage::disk('documents')->assertMissing($oldPath);
    expect(Document::count())->toBe(1);
});

it('preserves the document type on replace and ignores a mismatched client type', function () {
    $user = User::factory()->candidate()->create();

    // Seed an existing single-value photo that must remain the only one.
    actingAsUser($user)->postJson('/api/candidate/documents', [
        'type' => 'photo',
        'file' => UploadedFile::fake()->image('photo.jpg'),
    ])->assertCreated();

    // A multi-value passport page we will try to replace with a bogus type.
    actingAsUser($user)->postJson('/api/candidate/documents', [
        'type' => 'passport',
        'file' => UploadedFile::fake()->image('page1.jpg'),
    ])->assertCreated();

    $passport = Document::where('type', 'passport')->firstOrFail();

    // Attempt to re-type the passport page into a second "photo" on replace.
    actingAsUser($user)->post("/api/candidate/documents/{$passport->id}", [
        'type' => 'photo',
        'file' => UploadedFile::fake()->image('page1-new.jpg'),
    ])->assertOk()->assertJsonPath('data.type', 'passport');

    // The client type is ignored: the row stays a passport, and the
    // single-value invariant holds (still exactly one photo).
    expect($passport->fresh()->type->value)->toBe('passport')
        ->and(Document::where('type', 'photo')->count())->toBe(1)
        ->and(Document::where('type', 'passport')->count())->toBe(1);
});
