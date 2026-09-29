<?php

namespace App\Http\Resources\Admin;

use App\Models\BulkExport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A single bulk-export row for the admin UI to poll.
 *
 * `ready` is true only while the ZIP is built AND unexpired; `download_url`
 * points at the streaming endpoint and is null unless the export is ready, so
 * the UI shows a download link only when the file can actually be fetched.
 *
 * @mixin BulkExport
 */
class BulkExportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $ready = $this->status === BulkExport::STATUS_READY && ! $this->isExpired();

        return [
            'id' => $this->id,
            'status' => $this->status,
            'progress' => (int) $this->progress,
            'candidate_count' => count($this->candidate_ids ?? []),
            'ready' => $ready,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'error' => $this->error,
            'created_at' => $this->created_at?->toIso8601String(),
            'download_url' => $ready ? "/api/admin/candidate-exports/{$this->id}/download" : null,
        ];
    }
}
