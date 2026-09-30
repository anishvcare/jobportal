<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\EmployerStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\EmployerResource;
use App\Models\EmployerProfile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin management of employer profiles: list, view, approve and suspend.
 *
 * All actions sit inside the role:admin group, so authorization is enforced by
 * middleware (guests 401, wrong role 403). Responses use the {data:...}
 * envelope; the list adds pagination under meta.
 */
class EmployerController extends Controller
{
    private const DEFAULT_PER_PAGE = 20;

    /**
     * @var list<string>
     */
    private const RELATIONS = ['user', 'country', 'state', 'district'];

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::in(EmployerStatus::values())],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? self::DEFAULT_PER_PAGE);

        $query = EmployerProfile::query()
            ->with(self::RELATIONS)
            ->withCount('jobPosts')
            ->orderByDesc('id');

        if (isset($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        $paginator = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'data' => EmployerResource::collection($paginator->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(EmployerProfile $employerProfile): EmployerResource
    {
        $employerProfile->load(self::RELATIONS)->loadCount('jobPosts');

        return new EmployerResource($employerProfile);
    }

    public function approve(EmployerProfile $employerProfile): EmployerResource
    {
        $employerProfile->update([
            'status' => EmployerStatus::Approved,
            'approved_at' => now(),
        ]);

        $employerProfile->load(self::RELATIONS)->loadCount('jobPosts');

        return new EmployerResource($employerProfile);
    }

    public function suspend(EmployerProfile $employerProfile): EmployerResource
    {
        $employerProfile->update([
            'status' => EmployerStatus::Suspended,
        ]);

        $employerProfile->load(self::RELATIONS)->loadCount('jobPosts');

        return new EmployerResource($employerProfile);
    }
}
