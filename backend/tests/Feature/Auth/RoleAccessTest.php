<?php

use App\Enums\Role;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    // Throwaway routes to exercise the role middleware for each role.
    Route::middleware(['api', 'auth:sanctum', 'role:candidate'])->get('/api/_test/candidate', fn () => 'ok');
    Route::middleware(['api', 'auth:sanctum', 'role:employer'])->get('/api/_test/employer', fn () => 'ok');
    Route::middleware(['api', 'auth:sanctum', 'role:admin,employer'])->get('/api/_test/staff', fn () => 'ok');
});

it('returns 401 for guests on protected endpoints', function (string $uri) {
    $this->getJson($uri)->assertUnauthorized();
})->with(['/api/me', '/api/admin/dashboard', '/api/_test/candidate']);

it('lets new users choose candidate or employer exactly once', function () {
    $user = User::factory()->create();

    actingAsUser($user)->postJson('/api/onboarding/role', ['role' => 'employer'])
        ->assertOk()
        ->assertJsonPath('data.role', 'employer')
        ->assertJsonPath('data.needs_onboarding', false);

    actingAsUser($user->fresh())->postJson('/api/onboarding/role', ['role' => 'candidate'])
        ->assertForbidden();

    expect($user->fresh()->role)->toBe(Role::Employer);
});

it('never lets a user make themselves admin', function () {
    $user = User::factory()->create();

    actingAsUser($user)->postJson('/api/onboarding/role', ['role' => 'admin'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('role');

    expect($user->fresh()->role)->toBeNull();
});

it('blocks users who have not onboarded from role areas', function () {
    actingAsUser(User::factory()->create())->getJson('/api/_test/candidate')
        ->assertForbidden()
        ->assertJsonPath('code', 'onboarding_required');
});

it('enforces roles on every area', function (string $uri, ?string $allowedRole) {
    foreach ([Role::Candidate, Role::Employer, Role::Admin] as $role) {
        $user = User::factory()->role($role)->create();
        $expected = in_array($role->value, explode(',', (string) $allowedRole), true) ? 200 : 403;

        actingAsUser($user)->getJson($uri)->assertStatus($expected);
    }
})->with([
    'candidate area' => ['/api/_test/candidate', 'candidate'],
    'employer area' => ['/api/_test/employer', 'employer'],
    'admin or employer area' => ['/api/_test/staff', 'admin,employer'],
    'admin dashboard' => ['/api/admin/dashboard', 'admin'],
]);

it('shows admins summary counts', function () {
    User::factory()->candidate()->count(3)->create();
    User::factory()->employer()->count(2)->create();
    User::factory()->create();

    actingAsUser(User::factory()->admin()->create())->getJson('/api/admin/dashboard')
        ->assertOk()
        ->assertJson(['data' => ['candidates' => 3, 'employers' => 2, 'pending_onboarding' => 1]]);
});

it('marks authenticated API responses as non-cacheable', function () {
    $response = actingAsUser(User::factory()->candidate()->create())->getJson('/api/me');

    expect($response->headers->get('Cache-Control'))->toContain('no-store');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
});
