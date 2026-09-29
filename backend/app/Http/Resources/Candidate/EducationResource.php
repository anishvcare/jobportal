<?php

namespace App\Http\Resources\Candidate;

use App\Models\CandidateEducation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CandidateEducation
 */
class EducationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'education_level_id' => $this->education_level_id,
            'institution' => $this->institution,
            'field_of_study' => $this->field_of_study,
            'year_completed' => $this->year_completed,
            'sort_order' => $this->sort_order,
        ];
    }
}
