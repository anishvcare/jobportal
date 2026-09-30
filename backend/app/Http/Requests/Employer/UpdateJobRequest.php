<?php

namespace App\Http\Requests\Employer;

use App\Models\JobCategory;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isEmployer();
    }

    /**
     * A PATCH edit: every field is optional and only validated when present.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:150'],
            'description' => ['sometimes', 'required', 'string'],

            'job_category_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('job_categories', 'id'),
                function (string $attribute, mixed $value, Closure $fail): void {
                    $isTrade = JobCategory::query()
                        ->whereKey($value)
                        ->whereNotNull('parent_id')
                        ->exists();

                    if (! $isTrade) {
                        $fail('The selected category must be a trade.');
                    }
                },
            ],

            'country_id' => ['sometimes', 'nullable', 'integer', Rule::exists('countries', 'id')],
            'state_id' => ['sometimes', 'nullable', 'integer', Rule::exists('states', 'id')],
            'district_id' => ['sometimes', 'nullable', 'integer', Rule::exists('districts', 'id')],
            'city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'education_level_id' => ['sometimes', 'nullable', 'integer', Rule::exists('education_levels', 'id')],

            'experience_min' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:80'],
            'experience_max' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:80'],

            'vacancies' => ['sometimes', 'integer', 'min:1'],

            'salary_min' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'salary_max' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'salary_currency' => ['sometimes', 'nullable', 'string', 'size:3'],

            'deadline' => ['sometimes', 'nullable', 'date', 'after_or_equal:today'],

            'skill_ids' => ['sometimes', 'nullable', 'array'],
            'skill_ids.*' => ['integer', Rule::exists('skills', 'id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $data = $validator->getData();

            $min = $data['experience_min'] ?? null;
            $max = $data['experience_max'] ?? null;
            if ($min !== null && $max !== null && (int) $max < (int) $min) {
                $validator->errors()->add('experience_max', 'The maximum experience must be greater than or equal to the minimum.');
            }

            $salaryMin = $data['salary_min'] ?? null;
            $salaryMax = $data['salary_max'] ?? null;
            if ($salaryMin !== null && $salaryMax !== null && (int) $salaryMax < (int) $salaryMin) {
                $validator->errors()->add('salary_max', 'The maximum salary must be greater than or equal to the minimum.');
            }
        });
    }
}
