<?php

namespace App\Http\Resources\Admin;

use App\Models\DownloadAudit;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A single download-audit row for the admin audit log.
 *
 * candidate_name is null when the candidate has been hard-deleted (the row
 * survives with candidate_profile_id NULL) or for bulk rows not tied to one
 * candidate. actor/candidateProfile are eager-loaded by the controller.
 *
 * @mixin DownloadAudit
 */
class DownloadAuditResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'actor' => $this->whenLoaded('actor', fn () => $this->actor
                ? ['id' => $this->actor->id, 'name' => $this->actor->name, 'email' => $this->actor->email]
                : null),
            'candidate_profile_id' => $this->candidate_profile_id,
            'candidate_name' => $this->whenLoaded('candidateProfile', fn () => $this->candidateProfile?->full_name),
            'kind' => $this->kind,
            'bulk_export_id' => $this->bulk_export_id,
            'document_id' => $this->document_id,
            'ip' => $this->ip,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
