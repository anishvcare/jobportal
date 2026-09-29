<?php

namespace App\Http\Requests\Candidate;

use Illuminate\Foundation\Http\FormRequest;

class SyncLanguagesRequest extends FormRequest
{
    /**
     * Allowed proficiency levels for a language.
     */
    public const PROFICIENCIES = ['basic', 'conversational', 'fluent', 'native'];

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
            'languages' => ['present', 'array', 'max:50'],
            'languages.*.id' => ['required', 'integer', 'exists:languages,id'],
            'languages.*.proficiency' => ['nullable', 'in:'.implode(',', self::PROFICIENCIES)],
        ];
    }
}
