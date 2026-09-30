<?php

namespace App\Http\Resources\Employer;

use App\Models\JobPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin JobPost
 */
class JobPostResource extends JsonResource
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
            'description' => $this->description,
            'job_category_id' => $this->job_category_id,
            'category' => $this->whenLoaded('jobCategory', fn () => $this->jobCategory?->name),
            'country_id' => $this->country_id,
            'state_id' => $this->state_id,
            'district_id' => $this->district_id,
            'city' => $this->city,
            'country' => $this->whenLoaded('country', fn () => $this->country?->name),
            'state' => $this->whenLoaded('state', fn () => $this->state?->name),
            'district' => $this->whenLoaded('district', fn () => $this->district?->name),
            'education_level_id' => $this->education_level_id,
            'education_level' => $this->whenLoaded('educationLevel', fn () => $this->educationLevel?->name),
            'experience_min' => $this->experience_min,
            'experience_max' => $this->experience_max,
            'vacancies' => $this->vacancies,
            'salary_min' => $this->salary_min,
            'salary_max' => $this->salary_max,
            'salary_currency' => $this->salary_currency,
            'deadline' => $this->deadline?->format('Y-m-d'),
            'published_at' => $this->published_at?->toIso8601String(),
            'is_hidden' => (bool) $this->is_hidden,
            'closed_at' => $this->closed_at?->toIso8601String(),
            'is_live' => $this->isLive(),
            'status' => $this->statusLabel(),
            'skills' => $this->whenLoaded('skills', fn () => $this->skills
                ->map(fn ($skill) => ['id' => $skill->id, 'name' => $skill->name])
                ->values()),
            // Populated by withCount('applications') on list/detail reads.
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
