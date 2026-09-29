<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SearchCandidatesRequest;
use App\Http\Resources\Admin\CandidateDetailResource;
use App\Http\Resources\Admin\CandidateSummaryResource;
use App\Models\CandidateProfile;
use App\Services\Admin\CandidateSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin candidate search, detail and photo-thumbnail endpoints.
 *
 * All actions sit inside the role:admin group, so authorization is enforced by
 * middleware (guests 401, wrong role 403). Responses use the {data:...}
 * envelope; the list adds pagination + per-trade counts under meta.
 */
class CandidateController extends Controller
{
    private const DEFAULT_PER_PAGE = 20;

    public function __construct(private readonly CandidateSearch $search) {}

    public function index(SearchCandidatesRequest $request): JsonResponse
    {
        $filters = $request->validated();

        $query = $this->search->query($filters);

        $this->applySorting($query, $filters);

        $perPage = (int) ($filters['per_page'] ?? self::DEFAULT_PER_PAGE);
        $paginator = $query->paginate($perPage)->appends($request->query());

        $tradeCounts = $this->tradeCounts($filters);

        return response()->json([
            'data' => CandidateSummaryResource::collection($paginator->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'trade_counts' => $tradeCounts,
            ],
        ]);
    }

    public function show(CandidateProfile $profile): CandidateDetailResource
    {
        $profile->load([
            'user',
            'country',
            'state',
            'district',
            'educations.educationLevel',
            'experiences.jobCategory',
            'skills',
            'languages',
            'preferredCategories',
            'preferredCountries',
            'documents',
            'candidatePack',
        ]);

        return new CandidateDetailResource($profile);
    }

    /**
     * Stream the candidate's photo thumbnail for admin search results.
     *
     * A photo VIEW is not a downloadable document, so it is intentionally not
     * audited (only document/resume/pack/bulk downloads are). Admin-only via
     * the route group.
     */
    public function photo(CandidateProfile $profile): StreamedResponse
    {
        $document = $profile->documents()
            ->where('type', DocumentType::Photo->value)
            ->orderBy('id')
            ->first();

        abort_if($document === null, Response::HTTP_NOT_FOUND);

        // Inline stream from the private disk; the disk path is never exposed.
        return Storage::disk($document->disk)->response(
            $document->path,
            $document->original_name,
            ['Content-Type' => $document->mime]
        );
    }

    /**
     * Apply sorting with a stable secondary sort by id. Derived columns
     * (experience/completeness) reuse the search service's SQL expressions.
     *
     * @param  Builder<CandidateProfile>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applySorting($query, array $filters): void
    {
        $sort = $filters['sort'] ?? 'created_at';
        $direction = ($filters['direction'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        switch ($sort) {
            case 'name':
                $query->orderBy('full_name', $direction);
                break;
            case 'age':
                // Older candidates have earlier dob: age asc == dob desc.
                $query->orderBy('dob', $direction === 'asc' ? 'desc' : 'asc');
                break;
            case 'experience':
                [$sql, $bindings] = $this->search->experienceSortExpression();
                $query->orderByRaw("({$sql}) {$direction}", $bindings);
                break;
            case 'completeness':
                [$sql, $bindings] = $this->search->completenessSortExpression();
                $query->orderByRaw("({$sql}) {$direction}", $bindings);
                break;
            case 'created_at':
            default:
                $query->orderBy('created_at', $direction);
                break;
        }

        // Stable, deterministic secondary sort.
        $query->orderBy('id', 'asc');
    }

    /**
     * Per-trade counts over the ENTIRE filtered set (not just the page).
     *
     * Runs a fresh filtered base joined to candidate_preferred_category and
     * grouped by trade, so paging never changes the totals.
     *
     * @param  array<string, mixed>  $filters
     * @return list<array{trade_id:int, trade_name:string, count:int}>
     */
    private function tradeCounts(array $filters): array
    {
        $base = $this->search->query($filters)->reorder()->getQuery();

        return DB::query()
            ->fromSub($base, 'filtered')
            ->join('candidate_preferred_category as cpc', 'cpc.candidate_profile_id', '=', 'filtered.id')
            ->join('job_categories as jc', 'jc.id', '=', 'cpc.job_category_id')
            ->groupBy('jc.id', 'jc.name')
            ->orderByDesc(DB::raw('count(*)'))
            ->orderBy('jc.name')
            ->get([
                'jc.id as trade_id',
                'jc.name as trade_name',
                DB::raw('count(*) as count'),
            ])
            ->map(fn ($row) => [
                'trade_id' => (int) $row->trade_id,
                'trade_name' => (string) $row->trade_name,
                'count' => (int) $row->count,
            ])
            ->all();
    }
}
