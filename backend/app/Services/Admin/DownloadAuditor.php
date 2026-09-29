<?php

namespace App\Services\Admin;

use App\Models\CandidateProfile;
use App\Models\DownloadAudit;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Central helper for writing download_audits rows.
 *
 * Every admin download path (document, resume, pack, bulk zip) records through
 * here so audit rows are shaped consistently: who (actor), whose (candidate
 * profile, nullable for bulk/deleted), what (kind), the originating request's
 * ip and a length-capped user agent. Callers record AFTER a successful
 * build/lookup, so a failed pack build is never audited as a completed
 * download.
 */
class DownloadAuditor
{
    /**
     * @param  array{document_id?:int|null, bulk_export_id?:int|null}  $extra
     */
    public function record(
        User $actor,
        string $kind,
        ?CandidateProfile $profile,
        Request $request,
        array $extra = []
    ): DownloadAudit {
        return DownloadAudit::query()->create([
            'actor_user_id' => $actor->id,
            'candidate_profile_id' => $profile?->id,
            'kind' => $kind,
            'document_id' => $extra['document_id'] ?? null,
            'bulk_export_id' => $extra['bulk_export_id'] ?? null,
            'ip' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 1000),
        ]);
    }
}
