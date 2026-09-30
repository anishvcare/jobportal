<?php

namespace App\Http\Resources\Admin;

use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Application
 */
class AdminApplicationResource extends JsonResource
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
            'job' => $this->whenLoaded('jobPost', fn () => [
                'id' => $this->jobPost?->id,
                'title' => $this->jobPost?->title,
                'slug' => $this->jobPost?->slug,
            ]),
            'candidate' => $this->whenLoaded('candidateProfile', fn () => [
                'id' => $this->candidateProfile?->id,
                'full_name' => $this->candidateProfile?->full_name,
                'user' => $this->candidateProfile?->relationLoaded('user')
                    ? [
                        'id' => $this->candidateProfile->user?->id,
                        'name' => $this->candidateProfile->user?->name,
                        'email' => $this->candidateProfile->user?->email,
                    ]
                    : null,
            ]),
        ];
    }
}
