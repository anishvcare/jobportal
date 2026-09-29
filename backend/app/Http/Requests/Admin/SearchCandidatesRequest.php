<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the optional query parameters for admin candidate search.
 *
 * Every filter is optional: an empty request returns the full, paginated
 * candidate list. Authorization is handled by the role:admin middleware on the
 * route group, so this request only shapes and validates input.
 */
class SearchCandidatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:100'],

            'education_level_id' => ['nullable', 'integer', Rule::exists('education_levels', 'id')],

            'skill_ids' => ['nullable', 'array'],
            'skill_ids.*' => ['integer', Rule::exists('skills', 'id')],

            'experience_min' => ['nullable', 'integer', 'min:0', 'max:80'],
            'experience_max' => ['nullable', 'integer', 'min:0', 'max:80'],

            // A trade (child job category). Groups are never valid search targets.
            'job_category_id' => ['nullable', 'integer', Rule::exists('job_categories', 'id')],

            'age_min' => ['nullable', 'integer', 'min:0', 'max:120'],
            'age_max' => ['nullable', 'integer', 'min:0', 'max:120'],

            'gender' => ['nullable', 'string', Rule::in(['male', 'female', 'other'])],

            'state_id' => ['nullable', 'integer', Rule::exists('states', 'id')],
            'district_id' => ['nullable', 'integer', Rule::exists('districts', 'id')],

            'language_ids' => ['nullable', 'array'],
            'language_ids.*' => ['integer', Rule::exists('languages', 'id')],

            // Passport filter: either the shorthand token or the explicit booleans.
            'passport' => ['nullable', 'string', Rule::in(['any', 'has', 'valid'])],

            'completeness_min' => ['nullable', 'integer', 'min:0', 'max:100'],

            // NOTE(M5): an applied-job filter (applied_job_id) belongs here once
            // the applications table exists. Omitted for now.

            'sort' => ['nullable', 'string', Rule::in(['name', 'age', 'experience', 'completeness', 'created_at'])],
            'direction' => ['nullable', 'string', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
