<?php

namespace App\Http\Requests\Candidate;

use App\Enums\DocumentType;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class UploadDocumentRequest extends FormRequest
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
            'type' => ['required', 'string', Rule::in(DocumentType::values())],
            // sort_order is intentionally NOT accepted from the client; the
            // controller derives it server-side (append = max + 1) so a client
            // cannot force a collision or an out-of-range order.
            'file' => [
                'required',
                'file',
                // Accept HEIC/HEIF here so the service can return the specific
                // "please convert" message instead of a generic mimes error.
                'mimetypes:image/jpeg,image/png,application/pdf,image/heic,image/heif',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! $value instanceof UploadedFile) {
                        return;
                    }

                    $type = DocumentType::tryFrom((string) $this->input('type'));

                    if ($type === null) {
                        return; // The 'type' rule reports the error.
                    }

                    $max = DocumentType::maxSizeBytes($type);

                    if ($value->getSize() > $max) {
                        $mb = (int) round($max / (1024 * 1024));
                        $fail("The file may not be larger than {$mb} MB.");
                    }
                },
            ],
        ];
    }

    public function documentType(): DocumentType
    {
        return DocumentType::from($this->validated('type'));
    }
}
