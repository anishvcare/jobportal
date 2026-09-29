<?php

namespace App\Http\Controllers\Api\Candidate;

use App\Http\Controllers\Api\Candidate\Concerns\ResolvesCandidateProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\UpdateProfileRequest;
use App\Http\Resources\Candidate\CandidateProfileResource;
use App\Services\Documents\CompletenessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    use ResolvesCandidateProfile;

    public function show(Request $request): CandidateProfileResource
    {
        $profile = $this->resolveProfile($request);
        $this->authorize('view', $profile);

        $profile->load($this->profileRelations());

        return new CandidateProfileResource($profile);
    }

    /**
     * Dedicated completeness indicator for the profile wizard UI.
     */
    public function completeness(Request $request, CompletenessService $service): JsonResponse
    {
        $profile = $this->resolveProfile($request);
        $this->authorize('view', $profile);

        $profile->load('documents');

        return response()->json(['data' => $service->compute($profile)]);
    }

    public function update(UpdateProfileRequest $request): CandidateProfileResource
    {
        $profile = $this->resolveProfile($request);
        $this->authorize('update', $profile);

        $profile->fill($request->safe()->except('consent'));

        // Consent is stamped exactly once and never overwritten afterwards.
        if ($request->boolean('consent') && $profile->consent_at === null) {
            $profile->consent_at = now();
            $profile->consent_version = config('nexus.consent_version');
        }

        $profile->save();
        $profile->load($this->profileRelations());

        return new CandidateProfileResource($profile);
    }
}
