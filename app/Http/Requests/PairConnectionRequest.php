<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class PairConnectionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => ['nullable', 'string', 'regex:/^\d{10,15}$/'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('phone')) {
            $this->merge(['phone' => Str::of((string) $this->input('phone'))->replaceMatches('/\D/', '')->value() ?: null]);
        }
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => 'Informe o número com DDI e DDD, apenas dígitos. Exemplo: 5511988887777.',
        ];
    }

    public function pairingPhone(): ?string
    {
        return $this->validated()['phone'] ?? null;
    }
}
