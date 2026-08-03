<?php

namespace App\Http\Requests;

use App\Domain\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class QuickReplyRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'shortcut' => [
                'required',
                'string',
                'max:40',
                Rule::unique('quick_replies', 'shortcut')
                    ->where('tenant_id', app(TenantContext::class)->id())
                    ->ignore($this->route('quickReply')),
            ],
            'category' => ['nullable', 'string', 'max:60'],
            'body' => ['required', 'string', 'max:4000'],
            'is_favorite' => ['boolean'],
            'is_shared' => ['boolean'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): array
    {
        return collect(parent::validated())->except('is_shared')->all();
    }
}
