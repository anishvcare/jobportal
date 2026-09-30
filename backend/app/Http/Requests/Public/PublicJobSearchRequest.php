<?php

namespace App\Http\Requests\Public;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the optional query parameters for the public job board.
 *
 * Every filter is optional: an empty request returns the full, paginated list
 * of live jobs. The board is public, so authorize() is always true.
 */
class PublicJobSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:100'],

            'country_id' => ['nullable', 'integer', Rule::exists('countries', 'id')],
            'state_id' => ['nullable', 'integer', Rule::exists('states', 'id')],
            'district_id' => ['nullable', 'integer', Rule::exists('districts', 'id')],

            // A trade (child job category) selected by id.
            'job_category_id' => ['nullable', 'integer', Rule::exists('job_categories', 'id')],
            // A category slug: may be a trade slug OR a group slug (matches all
            // child trades of that group). Resolved in the controller.
            'category' => ['nullable', 'string', 'max:100'],

            // A single years-of-experience value the candidate has: matches jobs
            // whose experience_min is null or <= this value.
            'experience' => ['nullable', 'integer', 'min:0', 'max:80'],

            // Minimum salary the candidate wants: matches jobs whose salary_max
            // is null or >= this value.
            'salary_min' => ['nullable', 'integer', 'min:0'],

            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
