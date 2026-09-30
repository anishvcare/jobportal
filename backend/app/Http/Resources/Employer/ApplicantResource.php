<?php

namespace App\Http\Resources\Employer;

use App\Enums\DocumentType;
use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * An application to one of the employer's own jobs, with a NON-sensitive
 * candidate summary. Employers get no documents and no candidate pack: the
 * resume is the only downloadable artifact (see the resume route/policy).
 *
 * @mixin Application
 */
class ApplicantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $profile = $this->candidateProfile;

        return [
            'id' => $this->id,
            'status' => $this->status->value,
            'cover_note' => $this->cover_note,
            'created_at' => $this->created_at?->toIso8601String(),
            'candidate' => [
                'id' => $profile?->id,
                'full_name' => $profile?->full_name,
                'age' => $profile?->dob !== null ? $profile->dob->age : null,
                'phone' => $profile?->phone,
                'district' => $profile?->relationLoaded('district') ? $profile->district?->name : null,
                'trades' => $profile?->relationLoaded('preferredCategories')
                    ? $profile->preferredCategories->map(fn ($category) => $category->name)->values()
                    : null,
                'photo_url' => $this->hasPhotoDocument()
                    ? "/api/employer/applications/{$this->id}/photo"
                    : null,
            ],
        ];
    }

    /**
     * Whether the candidate has a photo document, without a lazy per-row query
     * (respects strict mode). Reads the eager-loaded documents relation.
     */
    private function hasPhotoDocument(): bool
    {
        $profile = $this->candidateProfile;

        if ($profile === null || ! $profile->relationLoaded('documents')) {
            return false;
        }

        return $profile->documents->contains(fn ($document) => $document->type === DocumentType::Photo);
    }
}
