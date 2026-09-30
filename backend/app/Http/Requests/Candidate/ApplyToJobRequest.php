<?php

namespace App\Http\Requests\Candidate;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a candidate's application to a job. Authorization is enforced by
 * the role:candidate middleware on the route group.
 */
class ApplyToJobRequest extends FormRequest
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
            'cover_note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
