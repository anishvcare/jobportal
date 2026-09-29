<?php

namespace App\Http\Resources\Candidate;

use App\Models\Document;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Document
 */
class DocumentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'original_name' => $this->original_name,
            'mime' => $this->mime,
            'size' => $this->size,
            'page_count' => $this->page_count,
            'sort_order' => $this->sort_order,
            'created_at' => $this->created_at?->toIso8601String(),
            // Relative API path; never expose the storage path or disk.
            'download_url' => "/api/candidate/documents/{$this->id}/download",
        ];
    }
}
