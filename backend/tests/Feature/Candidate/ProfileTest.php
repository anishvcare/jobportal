<?php

use App\Models\CandidateProfile;
use App\Models\Country;
use App\Models\JobCategory;
use App\Models\Language;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Support\Facades\DB;

it('auto-creates and returns the profile on first fetch', function () {
    $user = User::factory()->candidate()->create();

    actingAsUser($user)->getJson('/api/candidate/profile')
        ->assertOk()
        ->assertJsonPath('data.wizard_step', 0);

    $this->assertDatabaseHas('candidate_profiles', ['user_id' => $user->id]);
});

it('autosaves a subset of fields and persists wizard_step', function () {
    $user = User::factory()->candidate()->create();

    actingAsUser($user)->patchJson('/api/candidate/profile', [
        'full_name' => 'Ravi Kumar',
        'wizard_step' => 3,
    ])
        ->assertOk()
        ->assertJsonPath('data.full_name', 'Ravi Kumar')
        ->assertJsonPath('data.wizard_step', 3);

    // A later PATCH of a different field must not clear the earlier value.
    actingAsUser($user)->patchJson('/api/candidate/profile', ['city' => 'Kochi'])
        ->assertOk()
        ->assertJsonPath('data.full_name', 'Ravi Kumar')
        ->assertJsonPath('data.city', 'Kochi');
});

it('stores the passport number encrypted and returns it decrypted', function () {
    $user = User::factory()->candidate()->create();

    actingAsUser($user)->patchJson('/api/candidate/profile', [
        'has_passport' => true,
        'passport_number' => 'A1234567',
    ])
        ->assertOk()
        ->assertJsonPath('data.passport_number', 'A1234567');

    $raw = DB::table('candidate_profiles')->where('user_id', $user->id)->value('passport_number');
    expect($raw)->not->toBeNull()->and($raw)->not->toContain('A1234567');
});

it('stamps consent exactly once', function () {
    $user = User::factory()->candidate()->create();

    actingAsUser($user)->patchJson('/api/candidate/profile', ['consent' => true])->assertOk();

    $profile = CandidateProfile::where('user_id', $user->id)->firstOrFail();
    $firstStamp = $profile->consent_at;
    expect($firstStamp)->not->toBeNull()
        ->and($profile->consent_version)->toBe(config('nexus.consent_version'));

    // A second consent PATCH must not move the timestamp.
    actingAsUser($user)->patchJson('/api/candidate/profile', ['consent' => true])->assertOk();

    expect(CandidateProfile::where('user_id', $user->id)->value('consent_at')->toIso8601String())
        ->toBe($firstStamp->toIso8601String());
});

it('blocks employers and admins from candidate endpoints', function () {
    actingAsUser(User::factory()->employer()->create())->getJson('/api/candidate/profile')->assertForbidden();
    actingAsUser(User::factory()->admin()->create())->getJson('/api/candidate/profile')->assertForbidden();
});

it('returns 401 for guests on candidate endpoints', function () {
    $this->getJson('/api/candidate/profile')->assertUnauthorized();
});

it('does not let a candidate touch another candidate education', function () {
    $owner = createCandidateWithProfile();
    $education = $owner->educations()->create(['institution' => 'St Mary', 'sort_order' => 0]);

    $intruder = User::factory()->candidate()->create();

    actingAsUser($intruder)->patchJson("/api/candidate/profile/educations/{$education->id}", [
        'institution' => 'Hacked',
    ])->assertForbidden();

    actingAsUser($intruder)->deleteJson("/api/candidate/profile/educations/{$education->id}")
        ->assertForbidden();
});

it('does not let a candidate touch another candidate experience', function () {
    $owner = createCandidateWithProfile();
    $experience = $owner->experiences()->create(['job_title' => 'Welder', 'sort_order' => 0]);

    $intruder = User::factory()->candidate()->create();

    actingAsUser($intruder)->patchJson("/api/candidate/profile/experiences/{$experience->id}", [
        'job_title' => 'Hacked',
    ])->assertForbidden();
});

it('creates education and experience for the owner', function () {
    $user = User::factory()->candidate()->create();

    actingAsUser($user)->postJson('/api/candidate/profile/educations', [
        'institution' => 'Govt College',
        'field_of_study' => 'Mechanical',
    ])
        ->assertCreated()
        ->assertJsonPath('data.institution', 'Govt College');

    $group = JobCategory::create(['name' => 'Skilled', 'slug' => 'skilled']);
    $trade = JobCategory::create(['name' => 'Welder', 'slug' => 'welder', 'parent_id' => $group->id]);

    actingAsUser($user)->postJson('/api/candidate/profile/experiences', [
        'job_title' => 'Senior Welder',
        'job_category_id' => $trade->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.job_title', 'Senior Welder');
});

it('rejects experience with a group category instead of a trade', function () {
    $user = User::factory()->candidate()->create();
    $group = JobCategory::create(['name' => 'Skilled', 'slug' => 'skilled']);

    actingAsUser($user)->postJson('/api/candidate/profile/experiences', [
        'job_title' => 'Welder',
        'job_category_id' => $group->id,
    ])->assertUnprocessable()->assertJsonValidationErrors('job_category_id');
});

it('creates skills on the fly and syncs them', function () {
    $user = User::factory()->candidate()->create();

    actingAsUser($user)->putJson('/api/candidate/profile/skills', [
        'skills' => ['Welding', 'welding', ' Pipe Fitting '],
    ])->assertOk();

    // "Welding" and "welding" collapse to one slug.
    expect(Skill::count())->toBe(2);
    $this->assertDatabaseHas('skills', ['slug' => 'welding']);
    $this->assertDatabaseHas('skills', ['slug' => 'pipe-fitting']);
});

it('syncs languages with proficiency', function () {
    $user = User::factory()->candidate()->create();
    $english = Language::create(['name' => 'English', 'code' => 'en']);

    actingAsUser($user)->putJson('/api/candidate/profile/languages', [
        'languages' => [['id' => $english->id, 'proficiency' => 'fluent']],
    ])
        ->assertOk()
        ->assertJsonPath('data.languages.0.proficiency', 'fluent');
});

it('caps preferred categories at three', function () {
    $user = User::factory()->candidate()->create();
    $group = JobCategory::create(['name' => 'Skilled', 'slug' => 'skilled']);
    $trades = collect(range(1, 4))->map(fn ($i) => JobCategory::create([
        'name' => "Trade {$i}", 'slug' => "trade-{$i}", 'parent_id' => $group->id,
    ]));

    actingAsUser($user)->putJson('/api/candidate/profile/preferred-categories', [
        'category_ids' => $trades->pluck('id')->all(),
    ])->assertUnprocessable()->assertJsonValidationErrors('category_ids');

    actingAsUser($user)->putJson('/api/candidate/profile/preferred-categories', [
        'category_ids' => $trades->take(3)->pluck('id')->all(),
    ])->assertOk()->assertJsonCount(3, 'data.preferred_categories');
});

it('caps preferred countries at three', function () {
    $user = User::factory()->candidate()->create();
    $countries = collect(range(1, 4))->map(fn ($i) => Country::create([
        'name' => "Country {$i}", 'iso2' => 'C'.$i,
    ]));

    actingAsUser($user)->putJson('/api/candidate/profile/preferred-countries', [
        'country_ids' => $countries->pluck('id')->all(),
    ])->assertUnprocessable()->assertJsonValidationErrors('country_ids');
});
