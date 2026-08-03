<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TagRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'color' => ['required', 'string', 'max:30'],
            'icon' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:500'],
            'automation' => ['nullable', 'array'],
            'automation.service_queue_id' => ['nullable', 'uuid', 'exists:service_queues,id'],
            'automation.close_conversation' => ['nullable', 'boolean'],
        ];
    }
}
