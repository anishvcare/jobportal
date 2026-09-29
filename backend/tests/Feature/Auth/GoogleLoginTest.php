<?php

use App\Enums\Role;
use App\Models\User;
use App\Services\Auth\LoginCodeService;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as GoogleUser;

beforeEach(function () {
    config([
        'nexus.frontend_url' => 'http://localhost:3000',
        'nexus.admin_emails' => ['boss@example.com'],
    ]);
});

function fakeGoogleUser(string $email, string $id = '1001', bool $verified = true): void
{
    $googleUser = (new GoogleUser)->map([
        'id' => $id,
        'name' => 'Test Person',
        'email' => $email,
        'avatar' => 'https://example.com/a.png',
    ])->setRaw(['email_verified' => $verified]);

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($googleUser);
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
}

/**
 * @return array{path: string, query: array<string, string>}
 */
function parseRedirect(string $location): array
{
    $parts = parse_url($location);
    parse_str($parts['query'] ?? '', $query);

    return ['path' => $parts['path'] ?? '', 'query' => $query];
}

it('redirects to Google and remembers a safe return path', function () {
    $response = $this->get('/auth/google/redirect?redirect=/jobs/driver-moscow');

    $response->assertRedirect();
    expect($response->headers->get('Location'))->toContain('accounts.google.com');
    expect(session('auth.redirect'))->toBe('/jobs/driver-moscow');
});

it('ignores unsafe return paths (open redirect protection)', function (string $bad) {
    $this->get('/auth/google/redirect?redirect='.urlencode($bad));

    expect(session('auth.redirect'))->toBeNull();
})->with(['https://evil.com', '//evil.com', '/\\evil.com', 'javascript:alert(1)']);

it('creates a new user and hands the frontend a single-use code', function () {
    fakeGoogleUser('new@example.com');

    $response = $this->withSession(['auth.redirect' => '/jobs'])->get('/auth/google/callback?code=x&state=y');

    $target = parseRedirect($response->headers->get('Location'));
    expect($response->headers->get('Location'))->toStartWith('http://localhost:3000/auth/callback')
        ->and($target['query']['redirect'])->toBe('/jobs')
        ->and($target['query']['code'])->toHaveLength(64);

    $user = User::where('email', 'new@example.com')->first();
    expect($user)->not->toBeNull()
        ->and($user->google_id)->toBe('1001')
        ->and($user->role)->toBeNull();
});

it('links an existing account by email instead of duplicating it', function () {
    $existing = User::factory()->candidate()->create(['email' => 'same@example.com', 'google_id' => null]);
    fakeGoogleUser('SAME@example.com', '555');

    $this->get('/auth/google/callback');

    expect(User::count())->toBe(1)
        ->and($existing->fresh()->google_id)->toBe('555')
        ->and($existing->fresh()->role)->toBe(Role::Candidate);
});

it('makes ADMIN_EMAILS users admins on login', function () {
    fakeGoogleUser('boss@example.com');

    $this->get('/auth/google/callback');

    expect(User::where('email', 'boss@example.com')->first()->role)->toBe(Role::Admin);
});

it('rejects Google accounts without a verified email', function () {
    fakeGoogleUser('unverified@example.com', verified: false);

    $response = $this->get('/auth/google/callback');

    expect(parseRedirect($response->headers->get('Location')))
        ->toMatchArray(['path' => '/auth/login', 'query' => ['error' => 'unverified']]);
    expect(User::count())->toBe(0);
});

it('sends the user back to login when they cancel or the state expires', function () {
    $this->get('/auth/google/callback?error=access_denied')
        ->assertRedirect('http://localhost:3000/auth/login?error=cancelled');

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andThrow(new InvalidStateException);
    Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

    $this->get('/auth/google/callback')
        ->assertRedirect('http://localhost:3000/auth/login?error=expired');
});

it('exchanges a code for a session exactly once', function () {
    $user = User::factory()->candidate()->create();
    $code = app(LoginCodeService::class)->issue($user);

    $this->withHeaders(spaHeaders())
        ->postJson('/api/auth/exchange', ['code' => $code])
        ->assertOk()
        ->assertJsonPath('data.email', $user->email)
        ->assertJsonPath('data.role', 'candidate');

    $this->assertAuthenticatedAs($user, 'web');

    auth('web')->logout();

    $this->withHeaders(spaHeaders())
        ->postJson('/api/auth/exchange', ['code' => $code])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('code');
});

it('rejects expired codes', function () {
    $user = User::factory()->create();
    $code = app(LoginCodeService::class)->issue($user);

    $this->travel(config('nexus.login_code_ttl') + 5)->seconds();

    $this->withHeaders(spaHeaders())
        ->postJson('/api/auth/exchange', ['code' => $code])
        ->assertUnprocessable();
    $this->assertGuest('web');
});

it('demotes a former admin whose email was removed from ADMIN_EMAILS', function () {
    $user = User::factory()->admin()->create(['email' => 'former@example.com']);
    $code = app(LoginCodeService::class)->issue($user);

    $this->withHeaders(spaHeaders())
        ->postJson('/api/auth/exchange', ['code' => $code])
        ->assertOk()
        ->assertJsonPath('data.role', null)
        ->assertJsonPath('data.needs_onboarding', true);
});

it('logs out and returns 401 from /me afterwards', function () {
    $user = User::factory()->candidate()->create();

    $this->actingAs($user, 'web')->withHeaders(spaHeaders())
        ->getJson('/api/me')->assertOk()->assertJsonPath('data.id', $user->id);

    $this->withHeaders(spaHeaders())->postJson('/api/logout')->assertNoContent();

    $this->app['auth']->forgetGuards();
    $this->withHeaders(spaHeaders())->getJson('/api/me')->assertUnauthorized();
});
