<?php

use App\Models\Application;
use App\Models\EmployerProfile;
use App\Models\JobPost;
use App\Models\User;

it('includes job, application and pending-employer counts alongside the original keys', function () {
    User::factory()->create(); // not onboarded

    EmployerProfile::factory()->pending()->count(2)->create();
    EmployerProfile::factory()->approved()->create();

    // Three live jobs (JobPost factory publishes by default).
    JobPost::factory()->count(3)->create();
    Application::factory()->count(4)->create();

    $response = actingAsUser(User::factory()->admin()->create())->getJson('/api/admin/dashboard')
        ->assertOk()
        // Original keys must remain intact (RoleAccessTest contract).
        ->assertJsonPath('data.pending_onboarding', 1)
        // New keys.
        ->assertJsonPath('data.employers_pending', 2)
        ->assertJsonStructure(['data' => [
            'candidates', 'employers', 'pending_onboarding',
            'jobs', 'live_jobs', 'applications', 'employers_pending',
        ]]);

    // Each Application factory row creates its own job post, so there are at
    // least the 3 explicit jobs plus one per application; assert the counts
    // reflect the created applications and that jobs covers them.
    expect($response->json('data.applications'))->toBe(4)
        ->and($response->json('data.jobs'))->toBeGreaterThanOrEqual(3);
});
