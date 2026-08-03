<?php

namespace App\Http\Requests\Conversations;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SendMessageRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['nullable', 'string', 'max:4096', 'required_without:media'],
            'media' => ['nullable', 'file', 'max:20480'],
            'internal' => ['nullable', 'boolean'],
            'sign' => ['nullable', 'boolean'],
            'reply_to_message_id' => [
                'nullable', 'uuid',
                Rule::exists('messages', 'id')->where('conversation_id', $this->route('conversation')?->getKey()),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'body.required_without' => 'Escreva uma mensagem ou anexe um arquivo.',
        ];
    }
}
