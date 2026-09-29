<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Headers that make Sanctum treat a request as coming from the SPA
 * (so the session/cookie stack is applied to /api routes).
 *
 * @return array<string, string>
 */
function spaHeaders(): array
{
    return [
        'Origin' => 'http://localhost:3000',
        'Referer' => 'http://localhost:3000/',
        'Accept' => 'application/json',
    ];
}

function actingAsUser(User $user): TestCase
{
    // Sanctum caches the resolved user per app instance; reset so each call switches user.
    app('auth')->forgetGuards();

    return test()->actingAs($user, 'web');
}
