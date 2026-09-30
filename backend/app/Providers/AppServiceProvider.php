<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use RuntimeException;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Surface N+1 queries and mass-assignment mistakes during development and tests.
        Model::shouldBeStrict(! $this->app->isProduction());

        // Fail fast if a production deploy is running with insecure cookies.
        if ($this->app->isProduction()) {
            self::assertSecureSessionConfig();
        }

        $this->configureRateLimiting();
    }

    /**
     * Guard against a production boot with insecure session/cookie configuration.
     *
     * Gated on isProduction() by the caller so it never trips in local/testing.
     * Extracted as a static method so a test can invoke it deterministically.
     *
     * @throws RuntimeException
     */
    public static function assertSecureSessionConfig(): void
    {
        if (config('session.secure') !== true) {
            throw new RuntimeException(
                'Insecure session configuration for production: SESSION_SECURE_COOKIE must be true.'
            );
        }

        if (empty(config('session.domain'))) {
            throw new RuntimeException(
                'Insecure session configuration for production: SESSION_DOMAIN must be set.'
            );
        }

        if (empty(array_filter((array) config('sanctum.stateful')))) {
            throw new RuntimeException(
                'Insecure session configuration for production: SANCTUM_STATEFUL_DOMAINS must be set.'
            );
        }
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        RateLimiter::for('public', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(180)
            ->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('uploads', fn (Request $request) => Limit::perMinute(30)
            ->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('downloads', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user()?->id ?: $request->ip()));

        // Photo thumbnails are VIEWS, not file downloads: a single admin search
        // page can render up to 100 thumbnails, which would blow the 60/min
        // 'downloads' budget mid-render. Give them a generous view-oriented
        // limit so a full page of thumbnails never 429s.
        RateLimiter::for('thumbnails', fn (Request $request) => Limit::perMinute(300)
            ->by($request->user()?->id ?: $request->ip()));
    }
}
