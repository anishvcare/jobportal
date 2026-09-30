<?php

use App\Enums\EmployerStatus;
use App\Models\EmployerProfile;
use App\Models\User;

// ---------------------------------------------------------------------------
// Access control
// ---------------------------------------------------------------------------

it('rejects guests on the employer management endpoints', function (string $method, string $uri) {
    $this->{$method}($uri)->assertUnauthorized();
})->with([
    ['getJson', '/api/admin/employers'],
    ['getJson', '/api/admin/employers/1'],
    ['postJson', '/api/admin/employers/1/approve'],
    ['postJson', '/api/admin/employers/1/suspend'],
]);

it('forbids non-admin roles from employer management', function (string $state) {
    $employer = EmployerProfile::factory()->pending()->create();
    $user = User::factory()->{$state}()->create();

    actingAsUser($user)->getJson('/api/admin/employers')->assertForbidden();
    actingAsUser($user)->getJson("/api/admin/employers/{$employer->id}")->assertForbidden();
    actingAsUser($user)->postJson("/api/admin/employers/{$employer->id}/approve")->assertForbidden();
    actingAsUser($user)->postJson("/api/admin/employers/{$employer->id}/suspend")->assertForbidden();
})->with(['candidate', 'employer']);

// ---------------------------------------------------------------------------
// Listing + lifecycle
// ---------------------------------------------------------------------------

it('lets an admin list employers with status and job counts', function () {
    EmployerProfile::factory()->pending()->create();
    EmployerProfile::factory()->approved()->create();

    $admin = User::factory()->admin()->create();

    $response = actingAsUser($admin)->getJson('/api/admin/employers')->assertOk();

    expect($response->json('data'))->toHaveCount(2)
        ->and($response->json('meta.total'))->toBe(2);

    $row = $response->json('data.0');
    expect($row)->toHaveKeys(['id', 'company_name', 'status', 'job_count']);
});

it('filters employers by status', function () {
    $pending = EmployerProfile::factory()->pending()->create();
    EmployerProfile::factory()->approved()->create();

    $admin = User::factory()->admin()->create();

    $response = actingAsUser($admin)->getJson('/api/admin/employers?status=pending')->assertOk();

    expect(collect($response->json('data'))->pluck('id')->all())->toBe([$pending->id]);
});

it('approves a pending employer', function () {
    $employer = EmployerProfile::factory()->pending()->create();
    $admin = User::factory()->admin()->create();

    actingAsUser($admin)->postJson("/api/admin/employers/{$employer->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'approved');

    $employer->refresh();
    expect($employer->status)->toBe(EmployerStatus::Approved)
        ->and($employer->approved_at)->not->toBeNull();
});

it('suspends an employer', function () {
    $employer = EmployerProfile::factory()->approved()->create();
    $admin = User::factory()->admin()->create();

    actingAsUser($admin)->postJson("/api/admin/employers/{$employer->id}/suspend")
        ->assertOk()
        ->assertJsonPath('data.status', 'suspended');

    expect($employer->refresh()->status)->toBe(EmployerStatus::Suspended);
});
