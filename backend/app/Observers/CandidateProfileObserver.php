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

    public function deleted(CandidateProfile $profile): void
    {
        $this->markStaleAndRebuild($profile->id);
    }
}
