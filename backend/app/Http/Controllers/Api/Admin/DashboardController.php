<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\EmployerStatus;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\EmployerProfile;
use App\Models\JobPost;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $byRole = User::query()
            ->selectRaw('role, count(*) as total')
            ->groupBy('role')
            ->pluck('total', 'role');

        return response()->json(['data' => [
            'candidates' => (int) ($byRole[Role::Candidate->value] ?? 0),
            'employers' => (int) ($byRole[Role::Employer->value] ?? 0),
            'pending_onboarding' => User::whereNull('role')->count(),
            'jobs' => JobPost::query()->count(),
            'live_jobs' => JobPost::query()->live()->count(),
            'applications' => Application::query()->count(),
            'employers_pending' => EmployerProfile::query()
                ->where('status', EmployerStatus::Pending->value)
                ->count(),
        ]]);
    }
}
