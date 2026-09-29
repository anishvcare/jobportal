<?php

namespace App\Http\Requests\Candidate;

use Illuminate\Foundation\Http\FormRequest;

class StoreEducationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isCandidate();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'education_level_id' => ['nullable', 'integer', 'exists:education_levels,id'],
            'institution' => ['nullable', 'string', 'max:191'],
            'field_of_study' => ['nullable', 'string', 'max:191'],
            'year_completed' => ['nullable', 'integer', 'between:1950,'.((int) date('Y') + 1)],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
