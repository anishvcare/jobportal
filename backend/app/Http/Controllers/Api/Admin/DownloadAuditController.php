<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\DownloadAuditResource;
use App\Models\DownloadAudit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Admin audit log of every document/pack download (who, whose, when).
 *
 * Admin-only via the route group. Rows are returned newest-first, paginated
 * with meta. actor and candidateProfile are eager-loaded (Model::shouldBeStrict
 * forbids lazy loading); candidateProfile may be null for hard-deleted
 * candidates or bulk rows.
 */
class DownloadAuditController extends Controller
{
    private const DEFAULT_PER_PAGE = 20;

    private const MAX_PER_PAGE = 100;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'kind' => ['sometimes', 'string', 'in:document,resume,pack,bulk_zip'],
            'candidate_profile_id' => ['sometimes', 'integer'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ]);

        $perPage = (int) ($validated['per_page'] ?? self::DEFAULT_PER_PAGE);

        $paginator = DownloadAudit::query()
            ->with([
                'actor:id,name,email',
                'candidateProfile:id,full_name',
            ])
            ->when(
                isset($validated['kind']),
                fn ($query) => $query->where('kind', $validated['kind'])
            )
            ->when(
                isset($validated['candidate_profile_id']),
                fn ($query) => $query->where('candidate_profile_id', $validated['candidate_profile_id'])
            )
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->appends($request->query());

        return response()->json([
            'data' => DownloadAuditResource::collection($paginator->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
