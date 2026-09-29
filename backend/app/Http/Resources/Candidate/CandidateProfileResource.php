<?php

namespace App\Http\Resources\Candidate;

use App\Models\CandidateProfile;
use App\Models\Language;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CandidateProfile
 */
class CandidateProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'dob' => $this->dob?->format('Y-m-d'),
            'gender' => $this->gender,
            'phone' => $this->phone,
            'whatsapp' => $this->whatsapp,
            'address' => $this->address,
            'city' => $this->city,
            'pincode' => $this->pincode,
            'country_id' => $this->country_id,
            'state_id' => $this->state_id,
            'district_id' => $this->district_id,
            'has_passport' => $this->has_passport,
            // The owner is the only one who can reach this resource, so the
            // decrypted passport number is safe to expose here.
            'passport_number' => $this->passport_number,
            'passport_expiry' => $this->passport_expiry?->format('Y-m-d'),
            'summary' => $this->summary,
            'wizard_step' => $this->wizard_step,
            'consent_at' => $this->consent_at?->toIso8601String(),
            'consent_version' => $this->consent_version,
            'educations' => EducationResource::collection($this->whenLoaded('educations')),
            'experiences' => ExperienceResource::collection($this->whenLoaded('experiences')),
            'skills' => $this->whenLoaded('skills', fn () => $this->skills
                ->map(fn ($skill) => ['id' => $skill->id, 'name' => $skill->name])
                ->values()),
            'languages' => $this->whenLoaded('languages', fn () => $this->languages
                ->map(fn (Language $language) => [
                    'id' => $language->id,
                    'name' => $language->name,
                    'code' => $language->code,
                    'proficiency' => $language->pivot->proficiency,
                ])
                ->values()),
            'preferred_categories' => $this->whenLoaded('preferredCategories', fn () => $this->preferredCategories
                ->map(fn ($category) => ['id' => $category->id, 'name' => $category->name])
                ->values()),
            'preferred_countries' => $this->whenLoaded('preferredCountries', fn () => $this->preferredCountries
                ->map(fn ($country) => ['id' => $country->id, 'name' => $country->name])
                ->values()),
        ];
    }
}
