<?php

use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\Public\LookupController;
use Illuminate\Support\Facades\Route;

/*
| Public, unauthenticated, cacheable endpoints.
*/
Route::prefix('public')->middleware('throttle:public')->group(function () {
    Route::get('lookups', [LookupController::class, 'index']);
    Route::get('countries/{country}/states', [LookupController::class, 'states']);
    Route::get('states/{state}/districts', [LookupController::class, 'districts']);
});

/*
| Authentication.
*/
Route::post('auth/exchange', [AuthController::class, 'exchange'])->middleware('throttle:login');

Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::get('me', [AuthController::class, 'me']);
    Route::post('logout', [AuthController::class, 'logout']);
    Route::post('onboarding/role', [OnboardingController::class, 'chooseRole']);

    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::get('dashboard', AdminDashboardController::class);
    });
});
