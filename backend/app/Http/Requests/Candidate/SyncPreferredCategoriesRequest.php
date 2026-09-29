<?php

namespace App\Http\Requests\Candidate;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncPreferredCategoriesRequest extends FormRequest
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
            'category_ids' => ['present', 'array', 'max:3'],
            'category_ids.*' => [
                'integer',
                // Only trades (child categories) may be preferred.
                Rule::exists('job_categories', 'id')->whereNotNull('parent_id'),
            ],
        ];
    }
}
