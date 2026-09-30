<?php

use App\Models\EmployerProfile;
use App\Models\JobCategory;
use App\Models\JobPost;
use App\Models\User;

/**
 * Create an employer User plus its EmployerProfile in one step, returning both.
 *
 * @param  'approved'|'pending'|'suspended'  $status
 * @return array{0: User, 1: EmployerProfile}
 */
function makeEmployer(string $status = 'approved'): array
{
    $user = User::factory()->employer()->create();
    $profile = EmployerProfile::factory()->{$status}()->create(['user_id' => $user->id]);

    return [$user, $profile];
}

/*
| Access control.
*/

it('rejects guests and non-employers on the employer job endpoints', function () {
    $this->getJson('/api/employer/jobs')->assertUnauthorized();

    foreach ([User::factory()->candidate()->create(), User::factory()->admin()->create()] as $user) {
        actingAsUser($user)->getJson('/api/employer/jobs')->assertForbidden();
        actingAsUser($user)->postJson('/api/employer/jobs', [])->assertForbidden();
    }
});

/*
| Job CRUD + ownership.
*/

it('lets an employer create a job with a trade category', function () {
    [$user] = makeEmployer('approved');
    $trade = JobCategory::factory()->create();

    actingAsUser($user)->postJson('/api/employer/jobs', [
        'title' => 'Site Electrician',
        'description' => 'Wire up new builds.',
        'job_category_id' => $trade->id,
        'vacancies' => 3,
    ])
        ->assertCreated()
        ->assertJsonPath('data.title', 'Site Electrician')
        ->assertJsonPath('data.status', 'draft');
});

it('rejects a job whose category is a group, not a trade', function () {
    [$user] = makeEmployer('approved');
    $group = JobCategory::factory()->group()->create();

    actingAsUser($user)->postJson('/api/employer/jobs', [
        'title' => 'Bad Job',
        'description' => 'x',
        'job_category_id' => $group->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('job_category_id');
});

it('shows an employer only their own jobs', function () {
    [$user, $profile] = makeEmployer('approved');
    JobPost::factory()->count(2)->create(['employer_profile_id' => $profile->id]);

    [, $otherProfile] = makeEmployer('approved');
    JobPost::factory()->create(['employer_profile_id' => $otherProfile->id]);

    actingAsUser($user)->getJson('/api/employer/jobs')
        ->assertOk()
        ->assertJsonPath('meta.total', 2);
});

it('forbids viewing or editing another employer\'s job', function () {
    [$user] = makeEmployer('approved');
    [, $otherProfile] = makeEmployer('approved');
    $foreign = JobPost::factory()->create(['employer_profile_id' => $otherProfile->id]);

    actingAsUser($user)->getJson("/api/employer/jobs/{$foreign->id}")->assertForbidden();
    actingAsUser($user)->patchJson("/api/employer/jobs/{$foreign->id}", ['title' => 'Hijack'])->assertForbidden();
    actingAsUser($user)->postJson("/api/employer/jobs/{$foreign->id}/publish")->assertForbidden();
    actingAsUser($user)->postJson("/api/employer/jobs/{$foreign->id}/close")->assertForbidden();
});

/*
| Publish / close guards.
*/

it('lets an approved employer publish a job', function () {
    [$user, $profile] = makeEmployer('approved');
    $job = JobPost::factory()->draft()->create(['employer_profile_id' => $profile->id]);

    actingAsUser($user)->postJson("/api/employer/jobs/{$job->id}/publish")
        ->assertOk()
        ->assertJsonPath('data.status', 'published')
        ->assertJsonPath('data.is_live', true);

    expect($job->fresh()->published_at)->not->toBeNull();
});

it('blocks a pending employer from publishing', function () {
    [$user, $profile] = makeEmployer('pending');
    $job = JobPost::factory()->draft()->create(['employer_profile_id' => $profile->id]);

    actingAsUser($user)->postJson("/api/employer/jobs/{$job->id}/publish")
        ->assertForbidden()
        ->assertJsonPath('message', 'Your account is pending approval.');

    expect($job->fresh()->published_at)->toBeNull();
});

it('blocks a suspended employer from publishing', function () {
    [$user, $profile] = makeEmployer('suspended');
    $job = JobPost::factory()->draft()->create(['employer_profile_id' => $profile->id]);

    actingAsUser($user)->postJson("/api/employer/jobs/{$job->id}/publish")->assertForbidden();

    expect($job->fresh()->published_at)->toBeNull();
});

it('rejects publishing a job whose deadline has passed', function () {
    [$user, $profile] = makeEmployer('approved');
    $job = JobPost::factory()->draft()->create([
        'employer_profile_id' => $profile->id,
        'deadline' => now()->subDay()->toDateString(),
    ]);

    actingAsUser($user)->postJson("/api/employer/jobs/{$job->id}/publish")
        ->assertStatus(422);

    expect($job->fresh()->published_at)->toBeNull();
});

it('lets an employer close their own job', function () {
    [$user, $profile] = makeEmployer('approved');
    $job = JobPost::factory()->create(['employer_profile_id' => $profile->id]);

    actingAsUser($user)->postJson("/api/employer/jobs/{$job->id}/close")
        ->assertOk()
        ->assertJsonPath('data.status', 'closed');

    expect($job->fresh()->closed_at)->not->toBeNull();
});

it('can update a job it owns', function () {
    [$user, $profile] = makeEmployer('approved');
    $job = JobPost::factory()->create(['employer_profile_id' => $profile->id]);

    actingAsUser($user)->patchJson("/api/employer/jobs/{$job->id}", [
        'title' => 'Updated Title',
        'vacancies' => 5,
    ])
        ->assertOk()
        ->assertJsonPath('data.title', 'Updated Title')
        ->assertJsonPath('data.vacancies', 5);
});
