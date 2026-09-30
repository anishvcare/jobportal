<?php

namespace App\Http\Resources\Public;

use App\Models\JobPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Compact job card for the public listing page.
 *
 * @mixin JobPost
 */
class JobListResource extends JsonResource
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
            'company_name' => $this->whenLoaded('employerProfile', fn () => $this->employerProfile?->company_name),
            'logo_url' => $this->whenLoaded('employerProfile', fn () => $this->logoUrl()),
            'category' => $this->whenLoaded('jobCategory', fn () => $this->jobCategory?->name),
            'location' => $this->locationString(),
            'salary_min' => $this->salary_min,
            'salary_max' => $this->salary_max,
            'salary_currency' => $this->salary_currency,
            'experience_min' => $this->experience_min,
            'experience_max' => $this->experience_max,
            'deadline' => $this->deadline?->format('Y-m-d'),
            'published_at' => $this->published_at?->toIso8601String(),
        ];
    }

    /**
     * Public URL for the employer logo, or null when none is set.
     */
    private function logoUrl(): ?string
    {
        $profile = $this->employerProfile;

        if ($profile === null || $profile->logo_path === null) {
            return null;
        }

        return Storage::disk($profile->logo_disk ?? 'logos')->url($profile->logo_path);
    }

    /**
     * A single "District, State, Country" style string from the loaded
     * location relations, skipping any missing parts.
     */
    private function locationString(): ?string
    {
        $parts = array_filter([
            $this->relationLoaded('district') ? $this->district?->name : null,
            $this->relationLoaded('state') ? $this->state?->name : null,
            $this->relationLoaded('country') ? $this->country?->name : null,
        ], fn ($part) => is_string($part) && $part !== '');

        return $parts === [] ? null : implode(', ', $parts);
    }
}
