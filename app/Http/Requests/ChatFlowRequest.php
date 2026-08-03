<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ChatFlowRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'nodes' => ['array'],
            'nodes.*.id' => ['required', 'string', 'max:60'],
            'nodes.*.type' => ['required', 'string', 'max:40'],
            'nodes.*.position' => ['required', 'array'],
            'nodes.*.data' => ['array'],
            'edges' => ['array'],
            'edges.*.id' => ['required', 'string', 'max:80'],
            'edges.*.source' => ['required', 'string', 'max:60'],
            'edges.*.target' => ['required', 'string', 'max:60'],
            'edges.*.sourceHandle' => ['nullable', 'string', 'max:60'],
            'triggers' => ['nullable', 'array'],
            'is_active' => ['boolean'],
        ];
    }
}
