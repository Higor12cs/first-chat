<?php

namespace App\Http\Requests;

use App\Domain\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CardRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('cards', 'name')
                    ->where('tenant_id', app(TenantContext::class)->id())
                    ->ignore($this->route('card')?->id),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:4000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nome',
            'body' => 'mensagem',
        ];
    }
}
