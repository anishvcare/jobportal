<?php

namespace App\Http\Resources\Candidate;

use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A candidate's own application, with enough of the job to render the
 * "My applications" list including whether the job is still open.
 *
 * @mixin Application
 */
class CandidateApplicationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'cover_note' => $this->cover_note,
            'created_at' => $this->created_at?->toIso8601String(),
            'job' => $this->when($this->relationLoaded('jobPost') && $this->jobPost !== null, fn () => [
                'id' => $this->jobPost->id,
                'title' => $this->jobPost->title,
                'slug' => $this->jobPost->slug,
                'company_name' => $this->jobPost->relationLoaded('employerProfile')
                    ? $this->jobPost->employerProfile?->company_name
                    : null,
                'is_live' => $this->jobPost->isLive(),
                'closed' => $this->jobPost->closed_at !== null,
            ]),
        ];
    }
}
