<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use Illuminate\Support\Facades\Route;

// The API has no HTML UI; send stray visitors to the frontend.
Route::get('/', fn () => redirect()->away(config('nexus.frontend_url')));

// Google OAuth (needs the web session for Socialite's state parameter).
Route::middleware('throttle:login')->group(function () {
    Route::get('auth/google/redirect', [GoogleAuthController::class, 'redirect'])->name('auth.google.redirect');
    Route::get('auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');
});
