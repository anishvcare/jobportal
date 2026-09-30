<?php

use App\Models\EmployerProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('logos');
    Storage::fake('documents');
});

/*
| Access control.
*/

it('rejects guests on the employer profile endpoints', function () {
    $this->getJson('/api/employer/profile')->assertUnauthorized();
    $this->patchJson('/api/employer/profile', [])->assertUnauthorized();
});

it('rejects non-employers on the employer profile endpoints', function () {
    foreach ([User::factory()->candidate()->create(), User::factory()->admin()->create()] as $user) {
        actingAsUser($user)->getJson('/api/employer/profile')->assertForbidden();
        actingAsUser($user)->patchJson('/api/employer/profile', [])->assertForbidden();
    }
});

/*
| Profile CRUD.
*/

it('creates an empty pending profile on first access', function () {
    $employer = User::factory()->employer()->create();

    actingAsUser($employer)->getJson('/api/employer/profile')
        ->assertOk()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.logo_url', null);

    expect(EmployerProfile::query()->where('user_id', $employer->id)->count())->toBe(1);
});

it('lets an employer update their own company profile', function () {
    $employer = User::factory()->employer()->create();

    actingAsUser($employer)->patchJson('/api/employer/profile', [
        'company_name' => 'Acme Builders',
        'contact_person' => 'Priya',
        'phone' => '+911234567890',
        'website' => 'https://acme.example',
    ])
        ->assertOk()
        ->assertJsonPath('data.company_name', 'Acme Builders')
        ->assertJsonPath('data.contact_person', 'Priya');

    // A candidate may not update the profile fields with an invalid website.
    actingAsUser($employer)->patchJson('/api/employer/profile', [
        'website' => 'not-a-url',
    ])->assertUnprocessable()->assertJsonValidationErrors('website');
});

/*
| Logo upload.
*/

it('stores a logo on the logos disk, never on documents', function () {
    $employer = User::factory()->employer()->create();
    EmployerProfile::factory()->create(['user_id' => $employer->id]);

    $response = actingAsUser($employer)->post('/api/employer/profile/logo', [
        'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
    ], spaHeaders());

    $response->assertOk();
    $logoUrl = $response->json('data.logo_url');
    expect($logoUrl)->not->toBeNull();

    $profile = $employer->fresh()->employerProfile;
    expect($profile->logo_disk)->toBe('logos')
        ->and($profile->logo_path)->not->toBeNull();

    Storage::disk('logos')->assertExists($profile->logo_path);
    // Nothing must ever land on the private documents disk.
    expect(Storage::disk('documents')->allFiles())->toBe([]);
});

it('replaces the logo and deletes the old file', function () {
    $employer = User::factory()->employer()->create();
    EmployerProfile::factory()->create(['user_id' => $employer->id]);

    actingAsUser($employer)->post('/api/employer/profile/logo', [
        'logo' => UploadedFile::fake()->image('first.png', 200, 200),
    ], spaHeaders())->assertOk();

    $firstPath = $employer->fresh()->employerProfile->logo_path;

    actingAsUser($employer)->post('/api/employer/profile/logo', [
        'logo' => UploadedFile::fake()->image('second.png', 200, 200),
    ], spaHeaders())->assertOk();

    $secondPath = $employer->fresh()->employerProfile->logo_path;

    expect($secondPath)->not->toBe($firstPath);
    Storage::disk('logos')->assertMissing($firstPath);
    Storage::disk('logos')->assertExists($secondPath);
});

it('rejects a non-image logo upload', function () {
    $employer = User::factory()->employer()->create();

    actingAsUser($employer)->post('/api/employer/profile/logo', [
        'logo' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
    ], spaHeaders())->assertUnprocessable()->assertJsonValidationErrors('logo');
});

it('removes the logo on delete', function () {
    $employer = User::factory()->employer()->create();
    EmployerProfile::factory()->create(['user_id' => $employer->id]);

    actingAsUser($employer)->post('/api/employer/profile/logo', [
        'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
    ], spaHeaders())->assertOk();

    $path = $employer->fresh()->employerProfile->logo_path;

    actingAsUser($employer)->deleteJson('/api/employer/profile/logo')
        ->assertOk()
        ->assertJsonPath('data.logo_url', null);

    Storage::disk('logos')->assertMissing($path);
    expect($employer->fresh()->employerProfile->logo_path)->toBeNull();
});
