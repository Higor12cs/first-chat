<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ServiceQueueRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'color' => ['required', 'string', 'max:30'],
            'icon' => ['nullable', 'string', 'max:40'],
            'priority' => ['integer', 'min:0', 'max:100'],
            'assignment_strategy' => ['required', Rule::in(['manual', 'round_robin', 'least_busy'])],
            'business_hours' => ['nullable', 'array'],
            'outside_hours_message' => ['nullable', 'string', 'max:1000'],
            'ai_objective_id' => ['nullable', 'uuid', 'exists:ai_objectives,id'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'users' => ['array'],
            'users.*' => ['uuid', 'exists:users,id'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): array
    {
        return collect(parent::validated())->except('users')->all();
    }
}
