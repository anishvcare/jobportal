<?php

namespace App\Http\Controllers\Api\Candidate;

use App\Enums\ApplicationStatus;
use App\Http\Controllers\Api\Candidate\Concerns\ResolvesCandidateProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\ApplyToJobRequest;
use App\Http\Resources\Candidate\CandidateApplicationResource;
use App\Models\Application;
use App\Models\JobPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Candidate-facing applications: apply to a live job, list "My applications",
 * and withdraw an application. All actions sit inside the role:candidate
 * group (guests 401, non-candidates 403).
 */
class ApplicationController extends Controller
{
    use ResolvesCandidateProfile;

    /**
     * @var list<string>
     */
    private const LIST_RELATIONS = [
        'jobPost',
        'jobPost.employerProfile',
    ];

    public function index(Request $request): JsonResponse
    {
        $profile = $this->resolveProfile($request);

        $applications = $profile->applications()
            ->with(self::LIST_RELATIONS)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'data' => CandidateApplicationResource::collection($applications)->resolve($request),
        ]);
    }

    /**
     * Apply to a job. {jobPost} binds by slug (JobPost's default route key) to
     * stay consistent with the public job URLs. The job must be live and the
     * candidate may only apply once (enforced by the unique index; a friendly
     * 409 is returned instead of a raw DB error on a repeat).
     */
    public function store(ApplyToJobRequest $request, JobPost $jobPost): JsonResponse
    {
        $profile = $this->resolveProfile($request);

        if (! $jobPost->isLive()) {
            return response()->json([
                'message' => 'This job is no longer accepting applications.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $alreadyApplied = $profile->applications()
            ->where('job_post_id', $jobPost->id)
            ->exists();

        if ($alreadyApplied) {
            return response()->json([
                'message' => 'You have already applied to this job.',
            ], Response::HTTP_CONFLICT);
        }

        $application = $profile->applications()->create([
            'job_post_id' => $jobPost->id,
            'status' => ApplicationStatus::Applied,
            'cover_note' => $request->validated('cover_note'),
        ]);

        $application->load(self::LIST_RELATIONS);

        return (new CandidateApplicationResource($application))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Withdraw an application. A candidate may only delete their own; another
     * candidate's application resolves to a 404 (never revealed as forbidden).
     */
    public function destroy(Request $request, Application $application): JsonResponse
    {
        $profile = $this->resolveProfile($request);

        if ($application->candidate_profile_id !== $profile->id) {
            abort(Response::HTTP_NOT_FOUND);
        }

        $application->delete();

        return response()->json(status: Response::HTTP_NO_CONTENT);
    }
}
