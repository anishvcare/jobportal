<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CreateBulkExportRequest;
use App\Http\Resources\Admin\BulkExportResource;
use App\Jobs\BuildBulkPackZip;
use App\Models\BulkExport;
use App\Models\DownloadAudit;
use App\Services\Admin\DownloadAuditor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin bulk ZIP exports of candidate packs.
 *
 * All actions sit inside the role:admin group, so the actor is guaranteed to
 * be an admin (guests 401, wrong role 403). Creating an export enqueues a
 * BuildBulkPackZip job; the UI polls show() until the export is ready, then
 * streams the ZIP from download(), which writes a bulk_zip audit row.
 *
 * Any admin may view/download any export (the audit records who fetched what);
 * the requested_by column records who created it.
 */
class CandidateExportController extends Controller
{
    public function __construct(private readonly DownloadAuditor $auditor) {}

    /**
     * Create a queued export for up to config('nexus.bulk_export.max_candidates')
     * candidates and dispatch the build job. Returns 201 with the queued row.
     */
    public function store(CreateBulkExportRequest $request): JsonResponse
    {
        $validated = $request->validated();

        $export = BulkExport::query()->create([
            'requested_by' => $request->user()->id,
            'candidate_ids' => array_values(array_map('intval', $validated['candidate_ids'])),
            'status' => BulkExport::STATUS_QUEUED,
            'progress' => 0,
        ]);

        BuildBulkPackZip::dispatch($export->id);

        return (new BulkExportResource($export))
            ->response($request)
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Return the export's current status/progress for polling.
     */
    public function show(BulkExport $export): BulkExportResource
    {
        return new BulkExportResource($export);
    }

    /**
     * Stream the finished ZIP. Only a READY, unexpired export with a file on
     * disk is downloadable; anything else is 410 Gone (or 404 when missing).
     * A successful stream writes a kind=bulk_zip audit row (candidate_profile_id
     * null; bulk_export_id set).
     */
    public function download(Request $request, BulkExport $export): StreamedResponse
    {
        abort_if(
            $export->status !== BulkExport::STATUS_READY || $export->isExpired(),
            Response::HTTP_GONE
        );

        abort_if(
            ! $export->path || ! Storage::disk($export->disk)->exists($export->path),
            Response::HTTP_NOT_FOUND
        );

        $this->auditor->record(
            $request->user(),
            DownloadAudit::KIND_BULK_ZIP,
            null,
            $request,
            ['bulk_export_id' => $export->id]
        );

        return Storage::disk($export->disk)->download(
            $export->path,
            'candidates-export.zip',
            ['Content-Type' => 'application/zip']
        );
    }
}
