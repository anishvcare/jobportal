<?php

namespace App\Http\Resources\Admin;

use App\Models\JobPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin JobPost
 */
class AdminJobResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'city' => $this->city,
            'category' => $this->whenLoaded('jobCategory', fn () => $this->jobCategory?->name),
            'country' => $this->whenLoaded('country', fn () => $this->country?->name),
            'vacancies' => $this->vacancies,
            'deadline' => $this->deadline?->format('Y-m-d'),
            'published_at' => $this->published_at?->toIso8601String(),
            'is_hidden' => (bool) $this->is_hidden,
            'closed_at' => $this->closed_at?->toIso8601String(),
            'is_live' => $this->isLive(),
            'status' => $this->statusLabel(),
            'employer' => $this->whenLoaded('employerProfile', fn () => [
                'id' => $this->employerProfile?->id,
                'company_name' => $this->employerProfile?->company_name,
                'status' => $this->employerProfile?->status?->value,
            ]),
            'application_count' => $this->when(
                $this->applications_count !== null,
                fn () => (int) $this->applications_count,
            ),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }

    /**
     * A single human-facing lifecycle label derived from the persisted state.
     */
    private function statusLabel(): string
    {
        if ($this->closed_at !== null) {
            return 'closed';
        }

        if ($this->published_at === null) {
            return 'draft';
        }

        if ($this->is_hidden) {
            return 'hidden';
        }

        if (! $this->isLive()) {
            return 'expired';
        }

        return 'published';
    }
}
