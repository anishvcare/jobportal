<?php

namespace App\Http\Controllers\Api\Employer;

use App\Enums\DocumentType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\UpdateApplicationStatusRequest;
use App\Http\Resources\Employer\ApplicantResource;
use App\Models\Application;
use App\Models\DownloadAudit;
use App\Models\JobPost;
use App\Services\Admin\DownloadAuditor;
use App\Services\Pdf\CandidatePackBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Employer views of the applicants to their OWN jobs.
 *
 * Steering: an employer sees only applicants to their own jobs and can
 * download the RESUME only. No endpoint here exposes candidate documents, the
 * candidate pack, or a candidate search.
 */
class ApplicantController extends Controller
{
    private const DEFAULT_PER_PAGE = 20;

    /**
     * The non-sensitive candidate summary loaded for the applicant list.
     *
     * @var list<string>
     */
    private const CANDIDATE_RELATIONS = [
        'candidateProfile.user',
        'candidateProfile.district',
        'candidateProfile.preferredCategories',
        'candidateProfile.documents',
    ];

    public function __construct(
        private readonly CandidatePackBuilder $builder,
        private readonly DownloadAuditor $auditor,
    ) {}

    /**
     * List the applications for one of the employer's own jobs.
     */
    public function index(Request $request, JobPost $jobPost): JsonResponse
    {
        $this->authorize('view', $jobPost);

        $perPage = (int) $request->integer('per_page', self::DEFAULT_PER_PAGE);
        $perPage = max(1, min($perPage, 100));

        $paginator = $jobPost->applications()
            ->with(self::CANDIDATE_RELATIONS)
            ->orderByDesc('id')
            ->paginate($perPage)
            ->appends($request->query());

        return response()->json([
            'data' => ApplicantResource::collection($paginator->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function updateStatus(UpdateApplicationStatusRequest $request, Application $application): ApplicantResource
    {
        $this->authorize('updateStatus', $application);

        $application->update(['status' => $request->validated('status')]);

        $application->load(self::CANDIDATE_RELATIONS);

        return new ApplicantResource($application);
    }

    /**
     * Stream the candidate's freshly rendered resume PDF (the only downloadable
     * artifact for employers). Mirrors Admin/CandidateDownloadController@resume;
     * the actor recorded in the audit is the EMPLOYER user.
     */
    public function resume(Request $request, Application $application): StreamedResponse
    {
        $this->authorize('downloadResume', $application);

        $application->loadMissing('candidateProfile');
        $profile = $application->candidateProfile;

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
     * Stream the candidate's photo thumbnail for the applicant list. A photo
     * VIEW is not a downloadable document, so it is intentionally not audited.
     */
    public function photo(Request $request, Application $application): StreamedResponse
    {
        $this->authorize('view', $application);

        $document = $application->candidateProfile
            ->documents()
            ->where('type', DocumentType::Photo->value)
            ->orderBy('id')
            ->first();

        abort_if($document === null, Response::HTTP_NOT_FOUND);

        return Storage::disk($document->disk)->response(
            $document->path,
            $document->original_name,
            ['Content-Type' => $document->mime]
        );
    }
}
