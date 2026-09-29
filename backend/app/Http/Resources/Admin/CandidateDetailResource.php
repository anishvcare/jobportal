<?php

namespace App\Http\Resources\Admin;

use App\Enums\DocumentType;
use App\Models\CandidateProfile;
use App\Models\Language;
use App\Services\Documents\CompletenessService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * Full candidate detail for the admin candidate page.
 *
 * The admin is authorized to read the decrypted passport number (it feeds the
 * downloadable candidate spreadsheet), so it is exposed here. Document metadata
 * points at the admin document download route (added in FEAT-003) and never
 * exposes storage disk paths.
 *
 * @mixin CandidateProfile
 */
class CandidateDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $today = Carbon::today();
        $passportValid = (bool) $this->has_passport
            && $this->passport_expiry !== null
            && $this->passport_expiry->greaterThanOrEqualTo($today);

        return [
            'id' => $this->id,
            'full_name' => $this->full_name,
            'dob' => $this->dob?->format('Y-m-d'),
            'age' => $this->dob !== null ? $this->dob->age : null,
            'gender' => $this->gender,
            'phone' => $this->phone,
            'whatsapp' => $this->whatsapp,
            'address' => $this->address,
            'city' => $this->city,
            'pincode' => $this->pincode,
            'summary' => $this->summary,
            'user' => $this->whenLoaded('user', fn () => [
                'id' => $this->user->id,
                'name' => $this->user->name,
                'email' => $this->user->email,
            ]),
            'country' => $this->whenLoaded('country', fn () => $this->country
                ? ['id' => $this->country->id, 'name' => $this->country->name]
                : null),
            'state' => $this->whenLoaded('state', fn () => $this->state
                ? ['id' => $this->state->id, 'name' => $this->state->name]
                : null),
            'district' => $this->whenLoaded('district', fn () => $this->district
                ? ['id' => $this->district->id, 'name' => $this->district->name]
                : null),
            'has_passport' => (bool) $this->has_passport,
            // Admin is authorized: reading the attribute decrypts it.
            'passport_number' => $this->passport_number,
            'passport_expiry' => $this->passport_expiry?->format('Y-m-d'),
            'passport_valid' => $passportValid,
            'educations' => $this->whenLoaded('educations', fn () => $this->educations
                ->map(fn ($education) => [
                    'id' => $education->id,
                    'institution' => $education->institution,
                    'field_of_study' => $education->field_of_study,
                    'year_completed' => $education->year_completed,
                    'education_level' => $education->relationLoaded('educationLevel') && $education->educationLevel
                        ? ['id' => $education->educationLevel->id, 'name' => $education->educationLevel->name, 'rank' => $education->educationLevel->rank]
                        : null,
                ])
                ->values()),
            'experiences' => $this->whenLoaded('experiences', fn () => $this->experiences
                ->map(fn ($experience) => [
                    'id' => $experience->id,
                    'job_title' => $experience->job_title,
                    'company' => $experience->company,
                    'start_date' => $experience->start_date?->format('Y-m-d'),
                    'end_date' => $experience->end_date?->format('Y-m-d'),
                    'is_current' => (bool) $experience->is_current,
                    'job_category' => $experience->relationLoaded('jobCategory') && $experience->jobCategory
                        ? ['id' => $experience->jobCategory->id, 'name' => $experience->jobCategory->name]
                        : null,
                ])
                ->values()),
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
            'documents' => $this->whenLoaded('documents', fn () => $this->documents
                ->map(fn ($document) => [
                    'id' => $document->id,
                    'type' => $document->type->value,
                    'original_name' => $document->original_name,
                    'mime' => $document->mime,
                    'size' => $document->size,
                    'page_count' => $document->page_count,
                    // Admin download route added in FEAT-003; never the disk path.
                    'download_url' => "/api/admin/candidates/{$this->id}/documents/{$document->id}/download",
                ])
                ->values()),
            'photo_url' => $this->hasPhotoDocument() ? "/api/admin/candidates/{$this->id}/photo" : null,
            'completeness' => app(CompletenessService::class)->compute($this->resource),
        ];
    }

    private function hasPhotoDocument(): bool
    {
        if ($this->relationLoaded('documents')) {
            return $this->documents->contains(fn ($document) => $document->type === DocumentType::Photo);
        }

        return $this->documents()->where('type', DocumentType::Photo->value)->exists();
    }
}
