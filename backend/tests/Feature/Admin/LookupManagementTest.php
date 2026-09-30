<?php

use App\Http\Controllers\Api\Public\LookupController as PublicLookupController;
use App\Models\Country;
use App\Models\EducationLevel;
use App\Models\JobCategory;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

// ---------------------------------------------------------------------------
// Access control
// ---------------------------------------------------------------------------

it('rejects guests on the lookup management endpoints', function (string $method, string $uri) {
    $this->{$method}($uri)->assertUnauthorized();
})->with([
    ['postJson', '/api/admin/lookups/job-categories'],
    ['postJson', '/api/admin/lookups/countries'],
    ['postJson', '/api/admin/lookups/education-levels'],
]);

it('forbids non-admin roles from lookup management', function (string $state) {
    $user = User::factory()->{$state}()->create();

    actingAsUser($user)->postJson('/api/admin/lookups/job-categories', ['name' => 'X'])->assertForbidden();
    actingAsUser($user)->postJson('/api/admin/lookups/countries', ['name' => 'X', 'iso2' => 'ZZ'])->assertForbidden();
    actingAsUser($user)->postJson('/api/admin/lookups/education-levels', ['name' => 'X', 'rank' => 1])->assertForbidden();
})->with(['candidate', 'employer']);

// ---------------------------------------------------------------------------
// Job categories
// ---------------------------------------------------------------------------

it('creates a group and a trade under it, and toggles active', function () {
    $admin = User::factory()->admin()->create();

    $group = actingAsUser($admin)->postJson('/api/admin/lookups/job-categories', ['name' => 'Skilled'])
        ->assertCreated()
        ->assertJsonPath('data.is_group', true)
        ->json('data.id');

    $trade = actingAsUser($admin)->postJson('/api/admin/lookups/job-categories', [
        'name' => 'Welder',
        'parent_id' => $group,
    ])
        ->assertCreated()
        ->assertJsonPath('data.is_group', false)
        ->json('data.id');

    actingAsUser($admin)->patchJson("/api/admin/lookups/job-categories/{$trade}", ['name' => 'Master Welder'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Master Welder');

    actingAsUser($admin)->postJson("/api/admin/lookups/job-categories/{$trade}/toggle")
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    expect(JobCategory::find($trade)->is_active)->toBeFalse();
});

it('flushes the public lookup cache when a job category changes', function () {
    // Prime the cache.
    Cache::put(PublicLookupController::CACHE_KEY, ['sentinel' => true], 3600);

    actingAsUser(User::factory()->admin()->create())
        ->postJson('/api/admin/lookups/job-categories', ['name' => 'Skilled'])
        ->assertCreated();

    expect(Cache::get(PublicLookupController::CACHE_KEY))->toBeNull();
});

// ---------------------------------------------------------------------------
// Countries
// ---------------------------------------------------------------------------

it('creates a country and toggles its active flag', function () {
    $admin = User::factory()->admin()->create();

    $id = actingAsUser($admin)->postJson('/api/admin/lookups/countries', [
        'name' => 'Qatar',
        'iso2' => 'qa',
        'has_states' => false,
    ])
        ->assertCreated()
        ->assertJsonPath('data.iso2', 'QA')
        ->json('data.id');

    actingAsUser($admin)->postJson("/api/admin/lookups/countries/{$id}/toggle")
        ->assertOk()
        ->assertJsonPath('data.is_active', false);

    expect(Country::find($id)->is_active)->toBeFalse();
});

// ---------------------------------------------------------------------------
// Education levels
// ---------------------------------------------------------------------------

it('creates, renames and reorders education levels', function () {
    $admin = User::factory()->admin()->create();

    $sslc = actingAsUser($admin)->postJson('/api/admin/lookups/education-levels', ['name' => 'SSLC', 'rank' => 1])
        ->assertCreated()->json('data.id');
    $degree = actingAsUser($admin)->postJson('/api/admin/lookups/education-levels', ['name' => 'Degree', 'rank' => 2])
        ->assertCreated()->json('data.id');

    actingAsUser($admin)->patchJson("/api/admin/lookups/education-levels/{$sslc}", ['name' => 'SSLC (10th)'])
        ->assertOk()
        ->assertJsonPath('data.name', 'SSLC (10th)');

    // Reorder: degree first, sslc second.
    actingAsUser($admin)->patchJson('/api/admin/lookups/education-levels/reorder', ['ids' => [$degree, $sslc]])
        ->assertOk();

    expect(EducationLevel::find($degree)->rank)->toBe(1)
        ->and(EducationLevel::find($sslc)->rank)->toBe(2);
});
