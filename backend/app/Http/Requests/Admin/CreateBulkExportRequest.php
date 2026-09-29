<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates a request to create a bulk candidate ZIP export.
 *
 * Authorization is handled by the role:admin route group, so this request only
 * validates the payload. The per-export cap is single-sourced from
 * config('nexus.bulk_export.max_candidates') (=50); more than that returns 422.
 */
class CreateBulkExportRequest extends FormRequest
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
            'candidate_ids' => ['required', 'array', 'min:1', 'max:'.$this->maxCandidates()],
            'candidate_ids.*' => ['integer', 'exists:candidate_profiles,id'],
        ];
    }

    /**
     * The server-side cap on candidates per export.
     */
    public function maxCandidates(): int
    {
        return (int) config('nexus.bulk_export.max_candidates', 50);
    }
}
