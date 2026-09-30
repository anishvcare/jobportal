<?php

use App\Providers\AppServiceProvider;

it('does not trip during boot in the testing environment', function () {
    // The guard is gated on isProduction() in boot(), so booting the app under
    // the testing environment (with dev-insecure cookies) must never throw.
    // The application was already booted for this test run without error.
    expect(app()->environment())->toBe('testing');
    expect(app()->isProduction())->toBeFalse();
});

it('throws when session cookies are not secure', function () {
    config([
        'session.secure' => false,
        'session.domain' => '.example.com',
        'sanctum.stateful' => ['example.com'],
    ]);

    AppServiceProvider::assertSecureSessionConfig();
})->throws(RuntimeException::class, 'SESSION_SECURE_COOKIE must be true');

it('throws when the session domain is empty', function () {
    config([
        'session.secure' => true,
        'session.domain' => null,
        'sanctum.stateful' => ['example.com'],
    ]);

    AppServiceProvider::assertSecureSessionConfig();
})->throws(RuntimeException::class, 'SESSION_DOMAIN must be set');

it('throws when no stateful sanctum domains are configured', function () {
    config([
        'session.secure' => true,
        'session.domain' => '.example.com',
        'sanctum.stateful' => [],
    ]);

    AppServiceProvider::assertSecureSessionConfig();
})->throws(RuntimeException::class, 'SANCTUM_STATEFUL_DOMAINS must be set');

it('passes with a fully secure production configuration', function () {
    config([
        'session.secure' => true,
        'session.domain' => '.example.com',
        'sanctum.stateful' => ['example.com'],
    ]);

    AppServiceProvider::assertSecureSessionConfig();

    expect(true)->toBeTrue();
});
