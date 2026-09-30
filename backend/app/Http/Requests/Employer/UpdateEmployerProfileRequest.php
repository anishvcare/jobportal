<?php

namespace App\Http\Requests\Employer;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployerProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isEmployer();
    }

    /**
     * Every field is optional so a PATCH can update a single field. The
     * per-object ownership check lives in the controller via the policy.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'company_name' => ['sometimes', 'nullable', 'string', 'max:191'],
            'contact_person' => ['sometimes', 'nullable', 'string', 'max:191'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'website' => ['sometimes', 'nullable', 'url', 'max:255'],
            'country_id' => ['sometimes', 'nullable', 'integer', Rule::exists('countries', 'id')],
            'state_id' => ['sometimes', 'nullable', 'integer', Rule::exists('states', 'id')],
            'district_id' => ['sometimes', 'nullable', 'integer', Rule::exists('districts', 'id')],
            'city' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }
}
