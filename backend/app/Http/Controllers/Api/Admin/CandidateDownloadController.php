<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\CandidatePack;
use App\Models\CandidateProfile;
use App\Models\Document;
use App\Models\DownloadAudit;
use App\Services\Admin\DownloadAuditor;
use App\Services\Pdf\CandidatePackBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Admin downloads of a candidate's uploaded document, generated resume and
 * merged candidate pack. All actions sit inside the role:admin group, so the
 * actor is guaranteed to be an admin (guests 401, wrong role 403); the disk
 * path is never exposed to the client.
 *
 * Every successful download writes a download_audits row via DownloadAuditor.
 * The audit is recorded AFTER a successful build/lookup so a failed pack build
 * (503) is never logged as a completed download.
 */
class CandidateDownloadController extends Controller
{
    public function __construct(
        private readonly CandidatePackBuilder $builder,
        private readonly DownloadAuditor $auditor,
    ) {}

    /**
     * Stream a candidate's uploaded document from its private disk.
     */
    public function document(Request $request, CandidateProfile $profile, Document $document): StreamedResponse
    {
        // The document must belong to this profile; a foreign document 404s on
        // this profile's path so the id-in-path cannot be used to enumerate.
        abort_unless($document->candidate_profile_id === $profile->id, Response::HTTP_NOT_FOUND);

        $this->auditor->record(
            $request->user(),
            DownloadAudit::KIND_DOCUMENT,
            $profile,
            $request,
            ['document_id' => $document->id]
        );

        return Storage::disk($document->disk)->download(
            $document->path,
            $document->original_name,
            ['Content-Type' => $document->mime]
        );
    }

    /**
     * Stream the freshly rendered resume PDF, generated from profile data.
     */
    public function resume(Request $request, CandidateProfile $profile): StreamedResponse
    {
        $bytes = $this->builder->renderResume($profile);

        $this->auditor->record($request->user(), DownloadAudit::KIND_RESUME, $profile, $request);

        return response()->streamDownload(
            fn () => print $bytes,
            'resume.pdf',
            [
                'Content-Type' => 'application/pdf',
                'Content-Length' => (string) strlen($bytes),
            ],
            'attachment'
        );
    }

    /**
     * Stream the cached candidate pack, building it synchronously first when
     * the cache is missing or stale. A build failure returns 503 and is NOT
     * audited: the audit is written only after a READY pack is confirmed.
     */
    public function pack(Request $request, CandidateProfile $profile): StreamedResponse|JsonResponse
    {
        $pack = $this->builder->ensureFresh($profile);

        if ($pack->status !== CandidatePack::STATUS_READY
            || ! $pack->path
            || ! Storage::disk($pack->disk)->exists($pack->path)) {
            return response()->json([
                'message' => 'The candidate pack could not be generated. Please try again shortly.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        $this->auditor->record($request->user(), DownloadAudit::KIND_PACK, $profile, $request);

        return Storage::disk($pack->disk)->download(
            $pack->path,
            'candidate-pack.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }
}
