<?php

namespace App\Http\Resources\Admin;

use App\Enums\DocumentType;
use App\Models\CandidateProfile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin CandidateProfile
 */
class CandidateSummaryResource extends JsonResource
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
            'age' => $this->dob !== null ? $this->dob->age : null,
            'gender' => $this->gender,
            'district' => $this->whenLoaded('district', fn () => $this->district?->name),
            'trades' => $this->whenLoaded('preferredCategories', fn () => $this->preferredCategories
                ->map(fn ($category) => $category->name)
                ->values()),
            // Derived by the search query (CandidateSearch); cast to int.
            'experience_years' => (int) ($this->experience_years ?? 0),
            'completeness' => (int) ($this->completeness_pct ?? 0),
            'has_passport' => (bool) $this->has_passport,
            'passport_valid' => $passportValid,
            'photo_url' => $this->hasPhotoDocument() ? "/api/admin/candidates/{$this->id}/photo" : null,
        ];
    }

    /**
     * Whether a photo-type document exists, without leaking disk paths and
     * without a per-row query.
     *
     * The list query (CandidateSearch::query) surfaces this via a `has_photo`
     * withExists column, so the whole page is a fixed number of queries. When
     * `documents` happens to be eager-loaded (e.g. reused elsewhere) we read
     * that instead. We never fall back to a lazy per-row exists() here, which
     * would reintroduce the N+1.
     */
    private function hasPhotoDocument(): bool
    {
        if ($this->relationLoaded('documents')) {
            return $this->documents->contains(fn ($document) => $document->type === DocumentType::Photo);
        }

        return (bool) $this->has_photo;
    }
}
