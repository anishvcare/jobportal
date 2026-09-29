<?php

namespace App\Http\Controllers\Api\Candidate;

use App\Http\Controllers\Api\Candidate\Concerns\ResolvesCandidateProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\StoreEducationRequest;
use App\Http\Resources\Candidate\EducationResource;
use App\Models\CandidateEducation;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class EducationController extends Controller
{
    use ResolvesCandidateProfile;

    public function store(StoreEducationRequest $request): EducationResource
    {
        $profile = $this->resolveProfile($request);
        $this->authorize('update', $profile);

        $data = $request->validated();
        $data['candidate_profile_id'] = $profile->id;
        $data['sort_order'] ??= (int) $profile->educations()->max('sort_order') + 1;

        $education = CandidateEducation::create($data);

        return new EducationResource($education);
    }

    public function update(StoreEducationRequest $request, CandidateEducation $education): EducationResource
    {
        $profile = $this->resolveProfile($request);
        $this->authorize('update', $profile);
        abort_unless($education->candidate_profile_id === $profile->id, Response::HTTP_FORBIDDEN);

        $education->update($request->validated());

        return new EducationResource($education);
    }

    public function destroy(Request $request, CandidateEducation $education): Response
    {
        $profile = $this->resolveProfile($request);
        $this->authorize('update', $profile);
        abort_unless($education->candidate_profile_id === $profile->id, Response::HTTP_FORBIDDEN);

        $education->delete();

        return response()->noContent();
    }
}
