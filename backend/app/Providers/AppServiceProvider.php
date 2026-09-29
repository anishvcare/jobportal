<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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

        $this->configureRateLimiting();
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
