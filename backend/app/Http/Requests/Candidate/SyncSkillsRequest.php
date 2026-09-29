<?php

namespace App\Http\Requests\Candidate;

use Illuminate\Foundation\Http\FormRequest;

class SyncSkillsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->isCandidate();
    }

    /**
     * Skills are accepted as an array of names (create-on-the-fly).
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'skills' => ['present', 'array', 'max:50'],
            'skills.*' => ['required', 'string', 'max:60'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $skills = $this->input('skills');

        if (is_array($skills)) {
            $this->merge([
                'skills' => array_values(array_filter(array_map(
                    fn ($skill) => is_string($skill) ? trim($skill) : $skill,
                    $skills,
                ), fn ($skill) => $skill !== '' && $skill !== null)),
            ]);
        }
    }
}
