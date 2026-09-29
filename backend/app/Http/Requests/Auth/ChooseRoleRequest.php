<?php

namespace App\Http\Requests\Auth;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ChooseRoleRequest extends FormRequest
{
    /**
     * Only users who have not picked a role yet may choose one.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->needsOnboarding();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::in(Role::selectable())],
        ];
    }
}
