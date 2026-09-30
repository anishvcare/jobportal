<?php

namespace App\Http\Controllers\Api\Employer;

use App\Enums\EmployerStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Employer\StoreJobRequest;
use App\Http\Requests\Employer\UpdateJobRequest;
use App\Http\Resources\Employer\JobPostResource;
use App\Models\EmployerProfile;
use App\Models\JobPost;
use App\Services\Candidate\SkillResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class JobController extends Controller
{
    private const DEFAULT_PER_PAGE = 20;

    /**
     * @var list<string>
     */
    private const RELATIONS = [
        'jobCategory',
        'country',
        'state',
        'district',
        'educationLevel',
        'skills',
    ];

    public function index(Request $request): JsonResponse
    {
        $profile = $this->resolveProfile($request);

        $perPage = (int) $request->integer('per_page', self::DEFAULT_PER_PAGE);
        $perPage = max(1, min($perPage, 100));

        $paginator = $profile->jobPosts()
            ->with(self::RELATIONS)
            ->withCount('applications')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->appends($request->query());

        return response()->json([
            'data' => JobPostResource::collection($paginator->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreJobRequest $request, SkillResolver $resolver): JsonResponse
    {
        $profile = $this->resolveProfile($request);

        $data = $request->safe()->except(['skill_ids', 'skills']);
        $data['slug'] = $this->uniqueSlug($request->string('title'));
        $data['vacancies'] = $data['vacancies'] ?? 1;

        $job = $profile->jobPosts()->create($data);

        $skillIds = $this->resolveSkillIds($request, $resolver);
        if ($skillIds !== null) {
            $job->skills()->sync($skillIds);
        }

        $job->load(self::RELATIONS)->loadCount('applications');

        return (new JobPostResource($job))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Request $request, JobPost $jobPost): JobPostResource
    {
        $this->authorize('view', $jobPost);

        $jobPost->load(self::RELATIONS)->loadCount('applications');

        return new JobPostResource($jobPost);
    }

    public function update(UpdateJobRequest $request, JobPost $jobPost, SkillResolver $resolver): JobPostResource
    {
        $this->authorize('update', $jobPost);

        $jobPost->fill($request->safe()->except(['skill_ids', 'skills']));
        $jobPost->save();

        $skillIds = $this->resolveSkillIds($request, $resolver);
        if ($skillIds !== null) {
            $jobPost->skills()->sync($skillIds);
        }

        $jobPost->load(self::RELATIONS)->loadCount('applications');

        return new JobPostResource($jobPost);
    }

    /**
     * Publish a job. Only an APPROVED employer may publish; a job whose
     * deadline has already passed cannot go live.
     */
    public function publish(Request $request, JobPost $jobPost): JsonResponse|JobPostResource
    {
        $this->authorize('publish', $jobPost);

        $jobPost->loadMissing('employerProfile');

        if ($jobPost->employerProfile?->status !== EmployerStatus::Approved) {
            return response()->json([
                'message' => 'Your account is pending approval.',
            ], Response::HTTP_FORBIDDEN);
        }

        if ($jobPost->deadline !== null
            && $jobPost->deadline->toDateString() < Carbon::today()->toDateString()) {
            return response()->json([
                'message' => 'This job cannot be published because its deadline has passed.',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $jobPost->update([
            'published_at' => now(),
            'is_hidden' => false,
            'closed_at' => null,
        ]);

        $jobPost->load(self::RELATIONS)->loadCount('applications');

        return new JobPostResource($jobPost);
    }

    public function close(Request $request, JobPost $jobPost): JobPostResource
    {
        $this->authorize('close', $jobPost);

        $jobPost->update(['closed_at' => now()]);

        $jobPost->load(self::RELATIONS)->loadCount('applications');

        return new JobPostResource($jobPost);
    }

    /**
     * Resolve the skill ids to sync from the request, or null when the request
     * carries no skills field (so the current attachments are left untouched).
     *
     * Free-text `skills` (names, created on the fly) take precedence over the
     * id-based `skill_ids` when both are present.
     *
     * @return array<int, int>|null
     */
    private function resolveSkillIds(Request $request, SkillResolver $resolver): ?array
    {
        if ($request->has('skills')) {
            return $resolver->resolve($request->input('skills') ?? []);
        }

        if ($request->has('skill_ids')) {
            return array_map('intval', $request->input('skill_ids') ?? []);
        }

        return null;
    }

    /**
     * Resolve the employer's own profile, creating an empty pending one on
     * first access so a brand-new employer can start drafting jobs.
     */
    private function resolveProfile(Request $request): EmployerProfile
    {
        $user = $request->user();

        return $user->employerProfile
            ?? $user->employerProfile()->create([
                'status' => EmployerStatus::Pending,
            ]);
    }

    private function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'job';

        do {
            $slug = $base.'-'.Str::lower(Str::random(6));
        } while (JobPost::query()->where('slug', $slug)->exists());

        return $slug;
    }
}
