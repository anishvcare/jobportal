<?php

namespace App\Http\Controllers\Api\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\PublicJobSearchRequest;
use App\Http\Resources\Public\JobDetailResource;
use App\Http\Resources\Public\JobListResource;
use App\Models\JobCategory;
use App\Models\JobPost;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public, cacheable job board. Only LIVE jobs are ever exposed (published,
 * not hidden, not closed, deadline not past - see JobPost::scopeLive()).
 *
 * Both actions return responses tagged public + cacheable via cached() so the
 * SecurityHeaders middleware keeps them cacheable (everything under
 * /api/public/* is left cacheable; the rest of the API is no-store).
 */
class JobController extends Controller
{
    private const DEFAULT_PER_PAGE = 20;

    private const MAX_PER_PAGE = 50;

    /**
     * Relations the list card reads. Partial column selects still include the
     * FK/PK columns so the relations resolve under strict mode.
     *
     * @var array<string, mixed>
     */
    private const LIST_RELATIONS = [
        'employerProfile:id,company_name,logo_disk,logo_path',
        'jobCategory:id,name,slug',
        'country:id,name',
        'state:id,name',
        'district:id,name',
    ];

    /**
     * Relations the detail view + JSON-LD read.
     *
     * @var list<string>
     */
    private const DETAIL_RELATIONS = [
        'employerProfile',
        'jobCategory',
        'country',
        'state',
        'district',
        'educationLevel',
        'skills',
    ];

    public function index(PublicJobSearchRequest $request): JsonResponse
    {
        $filters = $request->validated();

        $query = JobPost::query()
            ->live()
            ->with(self::LIST_RELATIONS);

        $this->applyKeyword($query, $filters['keyword'] ?? null);
        $this->applyLocation($query, $filters);
        $this->applyCategory($query, $filters['job_category_id'] ?? null, $filters['category'] ?? null);
        $this->applyExperience($query, $filters['experience'] ?? null);
        $this->applySalary($query, $filters['salary_min'] ?? null);

        $perPage = (int) ($filters['per_page'] ?? self::DEFAULT_PER_PAGE);
        $perPage = max(1, min($perPage, self::MAX_PER_PAGE));

        $paginator = $query
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->appends($request->query());

        return $this->cached([
            'data' => JobListResource::collection($paginator->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    /**
     * The {jobPost:slug} binding resolves any job by slug; a non-live job is
     * hidden from the public board, so return 404 rather than expose it.
     */
    public function show(JobPost $jobPost): JsonResponse
    {
        if (! $jobPost->isLive()) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $jobPost->load(self::DETAIL_RELATIONS);

        return $this->cached([
            'data' => (new JobDetailResource($jobPost))->resolve(request()),
        ]);
    }

    /**
     * Portable keyword search: bound LIKE on title OR description on every
     * driver (SQLite + MySQL). We deliberately do NOT rely on the optional
     * MySQL FULLTEXT index: InnoDB FULLTEXT does not reflect same-transaction
     * writes, which breaks correctness under test transactions.
     */
    private function applyKeyword(Builder $query, ?string $keyword): void
    {
        $keyword = is_string($keyword) ? trim($keyword) : '';

        if ($keyword === '') {
            return;
        }

        $query->where(function (Builder $q) use ($keyword) {
            $q->where('title', 'like', "%{$keyword}%")
                ->orWhere('description', 'like', "%{$keyword}%");
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applyLocation(Builder $query, array $filters): void
    {
        if (! empty($filters['country_id'])) {
            $query->where('country_id', $filters['country_id']);
        }

        if (! empty($filters['state_id'])) {
            $query->where('state_id', $filters['state_id']);
        }

        if (! empty($filters['district_id'])) {
            $query->where('district_id', $filters['district_id']);
        }
    }

    /**
     * Category filter. An explicit trade id wins. Otherwise a slug may name a
     * trade (exact match) OR a group (match every active child trade of it).
     */
    private function applyCategory(Builder $query, ?int $tradeId, ?string $slug): void
    {
        if ($tradeId !== null) {
            $query->where('job_category_id', $tradeId);

            return;
        }

        $slug = is_string($slug) ? trim($slug) : '';

        if ($slug === '') {
            return;
        }

        $category = JobCategory::query()->where('slug', $slug)->first();

        if ($category === null) {
            // Unknown slug: match nothing rather than everything.
            $query->whereRaw('1 = 0');

            return;
        }

        if ($category->isGroup()) {
            $tradeIds = JobCategory::query()
                ->where('parent_id', $category->id)
                ->pluck('id')
                ->all();

            $query->whereIn('job_category_id', $tradeIds ?: [0]);

            return;
        }

        $query->where('job_category_id', $category->id);
    }

    /**
     * Experience: the candidate has `value` years. Match jobs that require no
     * more than that, i.e. experience_min is null or <= value.
     */
    private function applyExperience(Builder $query, ?int $value): void
    {
        if ($value === null) {
            return;
        }

        $query->where(function (Builder $q) use ($value) {
            $q->whereNull('experience_min')
                ->orWhere('experience_min', '<=', $value);
        });
    }

    /**
     * Salary: the candidate wants at least `value`. Match jobs whose top of
     * range (salary_max) is null or >= value.
     */
    private function applySalary(Builder $query, ?int $value): void
    {
        if ($value === null) {
            return;
        }

        $query->where(function (Builder $q) use ($value) {
            $q->whereNull('salary_max')
                ->orWhere('salary_max', '>=', $value);
        });
    }

    /**
     * Wrap a payload in a public, cacheable JSON response so SecurityHeaders
     * leaves it cacheable (mirrors LookupController::cached()).
     *
     * @param  array<string, mixed>  $payload
     */
    private function cached(array $payload): JsonResponse
    {
        return response()->json($payload)
            ->setPublic()
            ->setMaxAge(300)
            ->setSharedMaxAge(3600);
    }
}
