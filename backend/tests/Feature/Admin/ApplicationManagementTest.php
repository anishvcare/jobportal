<?php

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\User;

// ---------------------------------------------------------------------------
// Access control
// ---------------------------------------------------------------------------

it('rejects guests on the application management endpoints', function (string $method, string $uri) {
    $this->{$method}($uri)->assertUnauthorized();
})->with([
    ['getJson', '/api/admin/applications'],
    ['patchJson', '/api/admin/applications/1/status'],
]);

it('forbids non-admin roles from application management', function (string $state) {
    $application = Application::factory()->create();
    $user = User::factory()->{$state}()->create();

    actingAsUser($user)->getJson('/api/admin/applications')->assertForbidden();
    actingAsUser($user)->patchJson("/api/admin/applications/{$application->id}/status", ['status' => 'shortlisted'])
        ->assertForbidden();
})->with(['candidate', 'employer']);

// ---------------------------------------------------------------------------
// Listing + status update
// ---------------------------------------------------------------------------

it('lets an admin list applications with job and candidate details', function () {
    Application::factory()->count(2)->create();

    $admin = User::factory()->admin()->create();

    $response = actingAsUser($admin)->getJson('/api/admin/applications')->assertOk();

    expect($response->json('data'))->toHaveCount(2)
        ->and($response->json('meta.total'))->toBe(2);

    $row = $response->json('data.0');
    expect($row)->toHaveKeys(['id', 'status', 'job', 'candidate']);
});

it('filters applications by job post', function () {
    $a = Application::factory()->create();
    Application::factory()->create();

    $admin = User::factory()->admin()->create();

    $response = actingAsUser($admin)->getJson("/api/admin/applications?job_post_id={$a->job_post_id}")->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$a->id]);
});

it('updates an application status to a valid value', function () {
    $application = Application::factory()->create();
    $admin = User::factory()->admin()->create();

    actingAsUser($admin)->patchJson("/api/admin/applications/{$application->id}/status", ['status' => 'selected'])
        ->assertOk()
        ->assertJsonPath('data.status', 'selected');

    expect($application->refresh()->status)->toBe(ApplicationStatus::Selected);
});

it('rejects an invalid application status', function () {
    $application = Application::factory()->create();
    $admin = User::factory()->admin()->create();

    actingAsUser($admin)->patchJson("/api/admin/applications/{$application->id}/status", ['status' => 'bogus'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
});
