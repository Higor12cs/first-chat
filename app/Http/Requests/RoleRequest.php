<?php

namespace App\Http\Requests;

use App\Support\Permissions\PermissionRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:500'],
            'is_default' => ['boolean'],
            'permissions' => ['array'],
            'permissions.*' => ['string', Rule::in(PermissionRegistry::keys())],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): array
    {
        return collect(parent::validated())->except('permissions')->all();
    }
}
