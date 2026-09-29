<?php

namespace App\Http\Controllers\Api\Candidate;

use App\Http\Controllers\Api\Candidate\Concerns\ResolvesCandidateProfile;
use App\Http\Controllers\Controller;
use App\Models\CandidatePack;
use App\Services\Pdf\CandidatePackBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Candidate-owned downloads for the auto-generated resume and the merged
 * candidate pack. Both endpoints are owner-only (enforced via the
 * CandidateProfilePolicy) and never expose the private disk path to clients.
 */
class PackController extends Controller
{
    use ResolvesCandidateProfile;

    public function __construct(private readonly CandidatePackBuilder $builder) {}

    /**
     * Stream the freshly rendered resume PDF, generated from profile data.
     */
    public function resume(Request $request): StreamedResponse
    {
        $profile = $this->resolveProfile($request);
        $this->authorize('view', $profile);

        $bytes = $this->builder->renderResume($profile);

        return response()->streamDownload(
            fn () => print $bytes,
            'resume.pdf',
            [
                'Content-Type' => 'application/pdf',
                'Content-Length' => (string) strlen($bytes),
            ],
            'inline'
        );
    }

    /**
     * Stream the cached candidate pack, building it synchronously first when
     * the cache is missing or stale (approved on-request build).
     */
    public function pack(Request $request): StreamedResponse|JsonResponse
    {
        $profile = $this->resolveProfile($request);
        $this->authorize('view', $profile);

        $pack = $this->builder->ensureFresh($profile);

        if ($pack->status !== CandidatePack::STATUS_READY
            || ! $pack->path
            || ! Storage::disk($pack->disk)->exists($pack->path)) {
            return response()->json([
                'message' => 'The candidate pack could not be generated. Please try again shortly.',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return Storage::disk($pack->disk)->download(
            $pack->path,
            'candidate-pack.pdf',
            ['Content-Type' => 'application/pdf']
        );
    }
}
