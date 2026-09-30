<?php

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\CandidateProfile;
use App\Models\JobPost;
use App\Models\User;

/**
 * A candidate User with an attached CandidateProfile. The apply/list
 * endpoints resolve the acting user's own profile.
 */
function applicantWithProfile(): CandidateProfile
{
    $user = User::factory()->candidate()->create();
    $profile = CandidateProfile::factory()->create(['user_id' => $user->id]);

    return $profile->load('user');
}

it('rejects a guest trying to apply with 401', function () {
    $job = JobPost::factory()->create();

    $this->postJson("/api/candidate/jobs/{$job->slug}/apply")
        ->assertUnauthorized();

    expect(Application::count())->toBe(0);
});

it('lets a candidate apply once to a live job', function () {
    $profile = applicantWithProfile();
    $job = JobPost::factory()->create();

    actingAsUser($profile->user)
        ->postJson("/api/candidate/jobs/{$job->slug}/apply", ['cover_note' => 'Keen to help'])
        ->assertCreated()
        ->assertJsonPath('data.status', ApplicationStatus::Applied->value)
        ->assertJsonPath('data.job.slug', $job->slug);

    $this->assertDatabaseHas('applications', [
        'job_post_id' => $job->id,
        'candidate_profile_id' => $profile->id,
        'status' => ApplicationStatus::Applied->value,
        'cover_note' => 'Keen to help',
    ]);
});

it('rejects a second apply and does not create a duplicate row', function () {
    $profile = applicantWithProfile();
    $job = JobPost::factory()->create();

    actingAsUser($profile->user)
        ->postJson("/api/candidate/jobs/{$job->slug}/apply")
        ->assertCreated();

    actingAsUser($profile->user)
        ->postJson("/api/candidate/jobs/{$job->slug}/apply")
        ->assertStatus(409);

    expect(Application::query()
        ->where('job_post_id', $job->id)
        ->where('candidate_profile_id', $profile->id)
        ->count())->toBe(1);
});

it('rejects applying to non-live jobs', function () {
    $draft = JobPost::factory()->draft()->create();
    $hidden = JobPost::factory()->hidden()->create();
    $closed = JobPost::factory()->closed()->create();
    $expired = JobPost::factory()->expired()->create();

    foreach ([$draft, $hidden, $closed, $expired] as $job) {
        $profile = applicantWithProfile();

        actingAsUser($profile->user)
            ->postJson("/api/candidate/jobs/{$job->slug}/apply")
            ->assertStatus(422);
    }

    expect(Application::count())->toBe(0);
});

it('forbids an employer or admin from using the candidate apply route', function () {
    $job = JobPost::factory()->create();

    actingAsUser(User::factory()->employer()->create())
        ->postJson("/api/candidate/jobs/{$job->slug}/apply")
        ->assertForbidden();

    actingAsUser(User::factory()->admin()->create())
        ->postJson("/api/candidate/jobs/{$job->slug}/apply")
        ->assertForbidden();

    expect(Application::count())->toBe(0);
});

it('lists the candidate own applications with statuses', function () {
    $profile = applicantWithProfile();

    Application::factory()->create(['candidate_profile_id' => $profile->id]);
    Application::factory()->shortlisted()->create(['candidate_profile_id' => $profile->id]);
    Application::factory()->selected()->create(['candidate_profile_id' => $profile->id]);

    // Another candidate's application must not appear.
    Application::factory()->create();

    $response = actingAsUser($profile->user)
        ->getJson('/api/candidate/applications')
        ->assertOk();

    $statuses = collect($response->json('data'))->pluck('status');

    expect($response->json('data'))->toHaveCount(3)
        ->and($statuses)->toContain(ApplicationStatus::Applied->value)
        ->toContain(ApplicationStatus::Shortlisted->value)
        ->toContain(ApplicationStatus::Selected->value);
});

it('lets a candidate withdraw only their own application', function () {
    $profile = applicantWithProfile();
    $mine = Application::factory()->create(['candidate_profile_id' => $profile->id]);
    $theirs = Application::factory()->create();

    actingAsUser($profile->user)
        ->deleteJson("/api/candidate/applications/{$mine->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('applications', ['id' => $mine->id]);

    // Cannot withdraw someone else's application.
    actingAsUser($profile->user)
        ->deleteJson("/api/candidate/applications/{$theirs->id}")
        ->assertNotFound();

    $this->assertDatabaseHas('applications', ['id' => $theirs->id]);
});
