<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateApplicationStatusRequest;
use App\Http\Resources\Admin\AdminApplicationResource;
use App\Models\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin management of every application: list (filterable) and status update.
 *
 * All actions sit inside the role:admin group. Responses use the {data:...}
 * envelope; the list adds pagination under meta.
 */
class ApplicationController extends Controller
{
    private const DEFAULT_PER_PAGE = 20;

    /**
     * @var list<string>
     */
    private const RELATIONS = ['jobPost', 'candidateProfile.user'];

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'job_post_id' => ['nullable', 'integer', Rule::exists('job_posts', 'id')],
            'candidate_profile_id' => ['nullable', 'integer', Rule::exists('candidate_profiles', 'id')],
            'status' => ['nullable', 'string', Rule::in(ApplicationStatus::values())],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? self::DEFAULT_PER_PAGE);

        $query = Application::query()
            ->with(self::RELATIONS)
            ->orderByDesc('id');

        if (isset($validated['job_post_id'])) {
            $query->where('job_post_id', $validated['job_post_id']);
        }

        if (isset($validated['candidate_profile_id'])) {
            $query->where('candidate_profile_id', $validated['candidate_profile_id']);
        }

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $paginator = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'data' => AdminApplicationResource::collection($paginator->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function updateStatus(UpdateApplicationStatusRequest $request, Application $application): AdminApplicationResource
    {
        $application->update(['status' => $request->validated('status')]);

        $application->load(self::RELATIONS);

        return new AdminApplicationResource($application);
    }
}
