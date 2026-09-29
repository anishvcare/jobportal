<?php

namespace App\Observers;

use App\Models\CandidateProfile;

/**
 * Marks the cached candidate pack stale and schedules a delayed rebuild when
 * the profile changes. Rebuilds run on the queue so the pack is usually ready
 * before anyone requests it.
 */
class CandidateProfileObserver
{
    use SchedulesPackRebuild;

    public function saved(CandidateProfile $profile): void
    {
        $this->markStaleAndRebuild($profile->id);
    }

    /**
     * A deleted profile has nothing left to rebuild: the candidate_packs row is
     * removed by the cascade and the account-delete flow deletes the cached
     * pack file. Dispatching BuildCandidatePack here would only queue wasted
     * work (the job no-ops on a missing profile), so we skip it.
     */
    public function deleted(CandidateProfile $profile): void
    {
        // Intentionally no rebuild dispatch; see method docblock.
    }
}
