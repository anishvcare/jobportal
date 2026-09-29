<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
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

        // Job and application counts are added with the jobs milestone.
        return response()->json(['data' => [
            'candidates' => (int) ($byRole[Role::Candidate->value] ?? 0),
            'employers' => (int) ($byRole[Role::Employer->value] ?? 0),
            'pending_onboarding' => User::whereNull('role')->count(),
        ]]);
    }
}
