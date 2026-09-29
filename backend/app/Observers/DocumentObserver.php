<?php

namespace App\Observers;

use App\Models\Document;

/**
 * Marks the owning profile's cached pack stale and schedules a delayed rebuild
 * whenever a document is added, replaced, reordered or removed.
 */
class DocumentObserver
{
    use SchedulesPackRebuild;

    public function created(Document $document): void
    {
        $this->markStaleAndRebuild($document->candidate_profile_id);
    }

    public function updated(Document $document): void
    {
        $this->markStaleAndRebuild($document->candidate_profile_id);
    }

    public function deleted(Document $document): void
    {
        $this->markStaleAndRebuild($document->candidate_profile_id);
    }
}
