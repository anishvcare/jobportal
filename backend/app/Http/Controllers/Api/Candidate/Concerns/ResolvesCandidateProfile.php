<?php

namespace App\Http\Controllers\Api\Candidate\Concerns;

use App\Models\CandidateProfile;
use Illuminate\Http\Request;

trait ResolvesCandidateProfile
{
    /**
     * Resolve the current candidate's profile, creating it on first access.
     * The wasRecentlyCreated flag is cleared so wrapping resources return a
     * 200 (these endpoints are idempotent reads/updates, not creations).
     */
    protected function resolveProfile(Request $request): CandidateProfile
    {
        $profile = $request->user()->candidateProfile()->firstOrCreate([]);

        if ($profile->wasRecentlyCreated) {
            // A freshly created row only holds the attributes we set, so
            // reload every column (respects preventAccessingMissingAttributes)
            // and drop the "recently created" flag so resources return 200.
            $profile = $profile->fresh();
            $profile->wasRecentlyCreated = false;
        }

        return $profile;
    }

    /**
     * Relations loaded for the full profile resource. Educations and
     * experiences are ordered by sort_order (plain hasMany relations).
     *
     * @return array<int|string, mixed>
     */
    protected function profileRelations(): array
    {
        return [
            'educations' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            'experiences' => fn ($query) => $query->orderBy('sort_order')->orderBy('id'),
            'skills',
            'languages',
            'preferredCategories',
            'preferredCountries',
            'documents' => fn ($query) => $query->orderBy('type')->orderBy('sort_order')->orderBy('id'),
        ];
    }
}
