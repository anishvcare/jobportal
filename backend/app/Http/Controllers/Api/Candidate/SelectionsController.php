<?php

namespace App\Http\Controllers\Api\Candidate;

use App\Http\Controllers\Api\Candidate\Concerns\ResolvesCandidateProfile;
use App\Http\Controllers\Controller;
use App\Http\Requests\Candidate\SyncLanguagesRequest;
use App\Http\Requests\Candidate\SyncPreferredCategoriesRequest;
use App\Http\Requests\Candidate\SyncPreferredCountriesRequest;
use App\Http\Requests\Candidate\SyncSkillsRequest;
use App\Http\Resources\Candidate\CandidateProfileResource;
use App\Models\CandidateProfile;
use App\Services\Candidate\SkillResolver;
use Illuminate\Http\Request;

class SelectionsController extends Controller
{
    use ResolvesCandidateProfile;

    public function syncSkills(SyncSkillsRequest $request, SkillResolver $resolver): CandidateProfileResource
    {
        $profile = $this->profile($request);

        $ids = $resolver->resolve($request->validated('skills', []));
        $profile->skills()->sync($ids);

        return $this->resource($profile);
    }

    public function syncLanguages(SyncLanguagesRequest $request): CandidateProfileResource
    {
        $profile = $this->profile($request);

        $sync = [];
        foreach ($request->validated('languages', []) as $language) {
            $sync[(int) $language['id']] = ['proficiency' => $language['proficiency'] ?? null];
        }
        $profile->languages()->sync($sync);

        return $this->resource($profile);
    }

    public function syncPreferredCategories(SyncPreferredCategoriesRequest $request): CandidateProfileResource
    {
        $profile = $this->profile($request);

        $ids = array_slice(array_values($request->validated('category_ids', [])), 0, 3);
        $profile->preferredCategories()->sync($ids);

        return $this->resource($profile);
    }

    public function syncPreferredCountries(SyncPreferredCountriesRequest $request): CandidateProfileResource
    {
        $profile = $this->profile($request);

        $ids = array_slice(array_values($request->validated('country_ids', [])), 0, 3);
        $profile->preferredCountries()->sync($ids);

        return $this->resource($profile);
    }

    private function profile(Request $request): CandidateProfile
    {
        $profile = $this->resolveProfile($request);
        $this->authorize('update', $profile);

        return $profile;
    }

    private function resource(CandidateProfile $profile): CandidateProfileResource
    {
        $profile->load($this->profileRelations());

        return new CandidateProfileResource($profile);
    }
}
