<?php

namespace App\Jobs;

use App\Models\CandidateProfile;
use App\Services\Pdf\CandidatePackBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Rebuilds a candidate's pack in the background. Carries only the profile id
 * (never the model) so it stays small and queue-serialisable, and is safe to
 * run more than once: it force-rebuilds only when the pack is actually stale.
 */
class BuildCandidatePack implements ShouldQueue
{
    use Queueable;

    /**
     * Number of attempts before the job is marked failed.
     */
    public int $tries = 3;

    /**
     * Seconds to wait between retries.
     *
     * @var list<int>
     */
    public array $backoff = [10, 30];

    public function __construct(public int $candidateProfileId) {}

    public function handle(CandidatePackBuilder $builder): void
    {
        $profile = CandidateProfile::query()->find($this->candidateProfileId);

        // The profile may have been deleted between dispatch and execution.
        if ($profile === null) {
            return;
        }

        // Nothing to depend on if qpdf is missing; log and no-op rather than
        // failing the job repeatedly.
        if (! $builder->mergerAvailable()) {
            Log::warning('Skipping candidate pack build; qpdf unavailable.', [
                'profile_id' => $this->candidateProfileId,
            ]);

            return;
        }

        if (! $builder->isStale($profile)) {
            return;
        }

        $builder->build($profile);
    }
}
