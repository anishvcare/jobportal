<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\AdminJobResource;
use App\Models\JobPost;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin management of every job post: list (filterable), hide/unhide, close.
 *
 * All actions sit inside the role:admin group. Admin routes bind {jobPost:id}
 * (see FEAT-002 route-key decision) so management URLs use stable ids.
 * Responses use the {data:...} envelope; the list adds pagination under meta.
 */
class JobController extends Controller
{
    private const DEFAULT_PER_PAGE = 20;

    /**
     * @var list<string>
     */
    private const RELATIONS = ['jobCategory', 'country', 'employerProfile'];

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'string', 'in:draft,published,hidden,closed,expired'],
            'employer_profile_id' => ['nullable', 'integer'],
            'keyword' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? self::DEFAULT_PER_PAGE);

        $query = JobPost::query()
            ->with(self::RELATIONS)
            ->withCount('applications')
            ->orderByDesc('id');

        if (isset($validated['employer_profile_id'])) {
            $query->where('employer_profile_id', $validated['employer_profile_id']);
        }

        if (isset($validated['keyword'])) {
            $keyword = trim($validated['keyword']);

            if ($keyword !== '') {
                // Portable LIKE match on the job title, on every driver.
                $query->where('title', 'like', "%{$keyword}%");
            }
        }

        $this->applyStatus($query, $validated['status'] ?? null);

        $paginator = $query->paginate($perPage)->appends($request->query());

        return response()->json([
            'data' => AdminJobResource::collection($paginator->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function hide(JobPost $jobPost): AdminJobResource
    {
        $jobPost->update(['is_hidden' => true]);

        return $this->fresh($jobPost);
    }

    public function unhide(JobPost $jobPost): AdminJobResource
    {
        $jobPost->update(['is_hidden' => false]);

        return $this->fresh($jobPost);
    }

    public function close(JobPost $jobPost): AdminJobResource
    {
        $jobPost->update(['closed_at' => now()]);

        return $this->fresh($jobPost);
    }

    /**
     * @param  Builder<JobPost>  $query
     */
    private function applyStatus(Builder $query, ?string $status): void
    {
        switch ($status) {
            case 'draft':
                $query->whereNull('published_at')->whereNull('closed_at');
                break;
            case 'closed':
                $query->whereNotNull('closed_at');
                break;
            case 'hidden':
                $query->whereNotNull('published_at')->whereNull('closed_at')->where('is_hidden', true);
                break;
            case 'published':
                $query->live();
                break;
            case 'expired':
                $query->whereNotNull('published_at')
                    ->whereNull('closed_at')
                    ->where('is_hidden', false)
                    ->whereNotNull('deadline')
                    ->whereDate('deadline', '<', now()->toDateString());
                break;
            default:
                // No status filter.
                break;
        }
    }

    private function fresh(JobPost $jobPost): AdminJobResource
    {
        $jobPost->load(self::RELATIONS)->loadCount('applications');

        return new AdminJobResource($jobPost);
    }
}
