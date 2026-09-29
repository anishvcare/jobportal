<?php

namespace App\Http\Controllers\Api\Candidate;

use App\Http\Controllers\Api\Candidate\Concerns\ResolvesCandidateProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\StoreExperienceRequest;
use App\Http\Resources\Candidate\ExperienceResource;
use App\Models\CandidateExperience;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ExperienceController extends Controller
{
    use ResolvesCandidateProfile;

    public function store(StoreExperienceRequest $request): ExperienceResource
    {
        $profile = $this->resolveProfile($request);
        $this->authorize('update', $profile);

        $data = $request->validated();
        $data['candidate_profile_id'] = $profile->id;
        $data['sort_order'] ??= (int) $profile->experiences()->max('sort_order') + 1;

        $experience = CandidateExperience::create($data);

        return new ExperienceResource($experience);
    }

    public function update(StoreExperienceRequest $request, CandidateExperience $experience): ExperienceResource
    {
        $profile = $this->resolveProfile($request);
        $this->authorize('update', $profile);
        abort_unless($experience->candidate_profile_id === $profile->id, Response::HTTP_FORBIDDEN);

        $experience->update($request->validated());

        return new ExperienceResource($experience);
    }

    public function destroy(Request $request, CandidateExperience $experience): Response
    {
        $profile = $this->resolveProfile($request);
        $this->authorize('update', $profile);
        abort_unless($experience->candidate_profile_id === $profile->id, Response::HTTP_FORBIDDEN);

        $experience->delete();

        return response()->noContent();
    }
}
