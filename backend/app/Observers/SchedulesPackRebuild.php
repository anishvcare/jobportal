<?php

namespace App\Observers;

use App\Jobs\BuildCandidatePack;
use App\Models\CandidatePack;

/**
 * Shared behaviour for observers that react to profile/document changes by
 * marking the cached pack stale and dispatching a delayed rebuild.
 *
 * The builder writes the candidate_packs row (a different model), so these
 * profile/document observers never re-fire from the builder's own writes, and
 * marking the row stale here updates candidate_packs (not the observed model),
 * which cannot loop back into this observer.
 */
trait SchedulesPackRebuild
{
    protected function markStaleAndRebuild(?int $profileId): void
    {
        if ($profileId === null) {
            return;
        }

        CandidatePack::query()
            ->where('candidate_profile_id', $profileId)
            ->update(['status' => CandidatePack::STATUS_STALE]);

        BuildCandidatePack::dispatch($profileId)
            ->delay(now()->addSeconds((int) config('nexus.pack_rebuild_delay', 10)));
    }
}
