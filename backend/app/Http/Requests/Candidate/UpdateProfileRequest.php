<?php

namespace App\Http\Requests\Candidate;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isCandidate();
    }

    /**
     * Every field is optional so a PATCH autosave of a single field only
     * validates the keys that are actually present.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'full_name' => ['sometimes', 'nullable', 'string', 'max:191'],
            'dob' => ['sometimes', 'nullable', 'date', 'before:today'],
            'gender' => ['sometimes', 'nullable', 'in:male,female,other'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'whatsapp' => ['sometimes', 'nullable', 'string', 'max:30'],
            'address' => ['sometimes', 'nullable', 'string', 'max:500'],
            'city' => ['sometimes', 'nullable', 'string', 'max:120'],
            'pincode' => ['sometimes', 'nullable', 'string', 'max:20'],
            'country_id' => ['sometimes', 'nullable', 'integer', 'exists:countries,id'],
            'state_id' => ['sometimes', 'nullable', 'integer', 'exists:states,id'],
            'district_id' => ['sometimes', 'nullable', 'integer', 'exists:districts,id'],
            'has_passport' => ['sometimes', 'boolean'],
            'passport_number' => ['sometimes', 'nullable', 'string', 'max:60'],
            'passport_expiry' => ['sometimes', 'nullable', 'date'],
            'summary' => ['sometimes', 'nullable', 'string', 'max:2000'],
            'wizard_step' => ['sometimes', 'integer', 'between:0,12'],
            'consent' => ['sometimes', 'boolean'],
        ];
    }
}
