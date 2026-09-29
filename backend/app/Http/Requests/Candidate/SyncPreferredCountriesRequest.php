<?php

namespace App\Http\Requests\Candidate;

use Illuminate\Foundation\Http\FormRequest;

class SyncPreferredCountriesRequest extends FormRequest
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
            'country_ids' => ['present', 'array', 'max:3'],
            'country_ids.*' => ['integer', 'exists:countries,id'],
        ];
    }
}
