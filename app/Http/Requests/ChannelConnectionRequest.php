<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChannelConnectionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'default_service_queue_id' => ['nullable', 'uuid', 'exists:service_queues,id'],
            'chat_flow_id' => ['nullable', 'uuid', 'exists:chat_flows,id'],
            'is_active' => ['boolean'],
        ];
    }
}
