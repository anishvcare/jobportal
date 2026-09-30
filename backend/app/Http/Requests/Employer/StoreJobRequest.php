<?php

namespace App\Http\Requests\Employer;

use App\Models\JobCategory;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isEmployer();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'description' => ['required', 'string'],

            // A job targets a trade (child category). Groups (parent_id null)
            // are never valid targets.
            'job_category_id' => [
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

            'country_id' => ['nullable', 'integer', Rule::exists('countries', 'id')],
            'state_id' => ['nullable', 'integer', Rule::exists('states', 'id')],
            'district_id' => ['nullable', 'integer', Rule::exists('districts', 'id')],
            'city' => ['nullable', 'string', 'max:120'],
            'education_level_id' => ['nullable', 'integer', Rule::exists('education_levels', 'id')],

            'experience_min' => ['nullable', 'integer', 'min:0', 'max:80'],
            'experience_max' => ['nullable', 'integer', 'min:0', 'max:80'],

            'vacancies' => ['nullable', 'integer', 'min:1'],

            'salary_min' => ['nullable', 'integer', 'min:0'],
            'salary_max' => ['nullable', 'integer', 'min:0'],
            'salary_currency' => ['nullable', 'string', 'size:3'],

            'deadline' => ['nullable', 'date', 'after_or_equal:today'],

            'skill_ids' => ['nullable', 'array'],
            'skill_ids.*' => ['integer', Rule::exists('skills', 'id')],
        ];
    }

    /**
     * Cross-field checks that Laravel's built-in rules cannot express: the max
     * of a range must be >= its min.
     */
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
