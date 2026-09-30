<?php

use App\Models\EmployerProfile;
use App\Models\JobPost;
use App\Models\User;

/**
 * Create a published (live) job for an approved employer.
 */
function adminManagedJob(array $overrides = []): JobPost
{
    $employer = EmployerProfile::factory()->approved()->create();

    return JobPost::factory()->for($employer)->create($overrides);
}

/** All slugs currently returned by the public job board. */
function publicJobSlugs(): array
{
    $response = test()->getJson('/api/public/jobs')->assertOk();

    return collect($response->json('data'))->pluck('slug')->all();
}

// ---------------------------------------------------------------------------
// Access control
// ---------------------------------------------------------------------------

it('rejects guests on the job management endpoints', function (string $method, string $uri) {
    $this->{$method}($uri)->assertUnauthorized();
})->with([
    ['getJson', '/api/admin/jobs'],
    ['postJson', '/api/admin/jobs/1/hide'],
    ['postJson', '/api/admin/jobs/1/unhide'],
    ['postJson', '/api/admin/jobs/1/close'],
]);

it('forbids non-admin roles from job management', function (string $state) {
    $job = adminManagedJob();
    $user = User::factory()->{$state}()->create();

    actingAsUser($user)->getJson('/api/admin/jobs')->assertForbidden();
    actingAsUser($user)->postJson("/api/admin/jobs/{$job->id}/hide")->assertForbidden();
    actingAsUser($user)->postJson("/api/admin/jobs/{$job->id}/close")->assertForbidden();
})->with(['candidate', 'employer']);

// ---------------------------------------------------------------------------
// Listing + lifecycle
// ---------------------------------------------------------------------------

it('lets an admin list jobs with employer and application counts', function () {
    adminManagedJob();
    adminManagedJob();

    $admin = User::factory()->admin()->create();

    $response = actingAsUser($admin)->getJson('/api/admin/jobs')->assertOk();

    expect($response->json('data'))->toHaveCount(2)
        ->and($response->json('meta.total'))->toBe(2);

    $row = $response->json('data.0');
    expect($row)->toHaveKeys(['id', 'title', 'status', 'employer', 'application_count']);
});

it('hides a job so it disappears from the public board, and unhide restores it', function () {
    $job = adminManagedJob();
    $admin = User::factory()->admin()->create();

    // Live before hiding.
    expect(publicJobSlugs())->toContain($job->slug);

    actingAsUser($admin)->postJson("/api/admin/jobs/{$job->id}/hide")
        ->assertOk()
        ->assertJsonPath('data.is_hidden', true);

    expect($job->refresh()->is_hidden)->toBeTrue();
    expect(publicJobSlugs())->not->toContain($job->slug);

    actingAsUser($admin)->postJson("/api/admin/jobs/{$job->id}/unhide")
        ->assertOk()
        ->assertJsonPath('data.is_hidden', false);

    expect(publicJobSlugs())->toContain($job->slug);
});

it('closes a job so it disappears from the public board', function () {
    $job = adminManagedJob();
    $admin = User::factory()->admin()->create();

    expect(publicJobSlugs())->toContain($job->slug);

    actingAsUser($admin)->postJson("/api/admin/jobs/{$job->id}/close")
        ->assertOk()
        ->assertJsonPath('data.status', 'closed');

    expect($job->refresh()->closed_at)->not->toBeNull();
    expect(publicJobSlugs())->not->toContain($job->slug);
});
