<?php

namespace App\Http\Requests\Employer;

use Illuminate\Foundation\Http\FormRequest;

class UploadLogoRequest extends FormRequest
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
            // A company logo is a small, non-sensitive image. It is stored on
            // the public 'logos' disk (never the private 'documents' disk).
            'logo' => ['required', 'file', 'image', 'mimes:jpeg,png,webp', 'max:2048'],
        ];
    }
}
