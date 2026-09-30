<?php

namespace App\Http\Resources\Public;

use App\Models\JobPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Full public job detail. Carries every field the SSR frontend needs for
 * meta tags and the JobPosting structured data (JSON-LD).
 *
 * @mixin JobPost
 */
class JobDetailResource extends JsonResource
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

            // JobPosting timing.
            'date_posted' => $this->published_at?->toIso8601String(),
            'valid_through' => $this->deadline?->toIso8601String(),
            'employment_type' => 'FULL_TIME',

            // JobPosting hiringOrganization.
            'hiring_organization' => [
                'name' => $this->employerProfile?->company_name,
                'logo_url' => $this->logoUrl(),
                'website' => $this->employerProfile?->website,
            ],

            // JobPosting jobLocation.
            'job_location' => [
                'city' => $this->city,
                'district' => $this->district?->name,
                'state' => $this->state?->name,
                'country' => $this->country?->name,
            ],

            // JobPosting baseSalary.
            'base_salary' => [
                'min' => $this->salary_min,
                'max' => $this->salary_max,
                'currency' => $this->salary_currency,
            ],

            'category' => $this->jobCategory?->name,
            'category_slug' => $this->jobCategory?->slug,
            'education_level' => $this->educationLevel?->name,
            'experience_min' => $this->experience_min,
            'experience_max' => $this->experience_max,
            'vacancies' => $this->vacancies,
            'skills' => $this->skills
                ->map(fn ($skill) => ['id' => $skill->id, 'name' => $skill->name])
                ->values(),
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
}
