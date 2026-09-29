<?php

use App\Http\Controllers\Api\Admin\CandidateController as AdminCandidateController;
use App\Http\Controllers\Api\Admin\CandidateDownloadController as AdminCandidateDownloadController;
use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\DownloadAuditController as AdminDownloadAuditController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\Candidate\AccountController;
use App\Http\Controllers\Api\Candidate\DocumentController;
use App\Http\Controllers\Api\Candidate\EducationController;
use App\Http\Controllers\Api\Candidate\ExperienceController;
use App\Http\Controllers\Api\Candidate\PackController;
use App\Http\Controllers\Api\Candidate\ProfileController;
use App\Http\Controllers\Api\Candidate\SelectionsController;
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

        // {profile} binds to CandidateProfile via the controller type hints.
        Route::get('candidates', [AdminCandidateController::class, 'index']);
        Route::get('candidates/{profile}', [AdminCandidateController::class, 'show']);
        Route::get('candidates/{profile}/photo', [AdminCandidateController::class, 'photo'])
            ->middleware('throttle:downloads');

        // Single-item downloads; each writes a download_audits row.
        Route::get('candidates/{profile}/documents/{document}/download', [AdminCandidateDownloadController::class, 'document'])
            ->middleware('throttle:downloads');
        Route::get('candidates/{profile}/resume', [AdminCandidateDownloadController::class, 'resume'])
            ->middleware('throttle:downloads');
        Route::get('candidates/{profile}/pack', [AdminCandidateDownloadController::class, 'pack'])
            ->middleware('throttle:downloads');

        Route::get('download-audits', [AdminDownloadAuditController::class, 'index']);
    });

    Route::prefix('candidate')->middleware('role:candidate')->group(function () {
        Route::get('profile', [ProfileController::class, 'show']);
        Route::patch('profile', [ProfileController::class, 'update']);

        Route::post('profile/educations', [EducationController::class, 'store']);
        Route::patch('profile/educations/{education}', [EducationController::class, 'update']);
        Route::delete('profile/educations/{education}', [EducationController::class, 'destroy']);

        Route::post('profile/experiences', [ExperienceController::class, 'store']);
        Route::patch('profile/experiences/{experience}', [ExperienceController::class, 'update']);
        Route::delete('profile/experiences/{experience}', [ExperienceController::class, 'destroy']);

        Route::put('profile/skills', [SelectionsController::class, 'syncSkills']);
        Route::put('profile/languages', [SelectionsController::class, 'syncLanguages']);
        Route::put('profile/preferred-categories', [SelectionsController::class, 'syncPreferredCategories']);
        Route::put('profile/preferred-countries', [SelectionsController::class, 'syncPreferredCountries']);

        Route::get('profile/completeness', [ProfileController::class, 'completeness']);

        Route::get('documents', [DocumentController::class, 'index']);
        Route::post('documents', [DocumentController::class, 'store'])->middleware('throttle:uploads');
        Route::patch('documents/reorder', [DocumentController::class, 'reorder'])->middleware('throttle:uploads');
        Route::post('documents/{document}', [DocumentController::class, 'update'])->middleware('throttle:uploads');
        Route::delete('documents/{document}', [DocumentController::class, 'destroy']);
        Route::get('documents/{document}/download', [DocumentController::class, 'download'])->middleware('throttle:downloads');

        Route::get('resume', [PackController::class, 'resume'])->middleware('throttle:downloads');
        Route::get('pack', [PackController::class, 'pack'])->middleware('throttle:downloads');

        Route::delete('account', [AccountController::class, 'destroy']);
    });
});
