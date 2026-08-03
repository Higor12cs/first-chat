<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AiObjectiveRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'provider' => ['required', 'string', Rule::in(array_keys(config('ai.providers', [])))],
            'model' => ['required', 'string', 'max:120', Rule::in($this->allowedModels())],
            'temperature' => ['required', 'numeric', 'min:0', 'max:2'],
            'max_tokens' => ['required', 'integer', 'min:64', 'max:32000'],
            'system_prompt' => ['required', 'string', 'max:20000'],
            'tools' => ['array'],
            'tools.*' => ['string', 'max:60'],
            'cost_limit_cents' => ['nullable', 'integer', 'min:0'],
            'max_turns' => ['required', 'integer', 'min:1', 'max:200'],
            'handoff_service_queue_id' => ['nullable', 'uuid', 'exists:service_queues,id'],
            'closing_condition' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<int, string>
     */
    private function allowedModels(): array
    {
        return collect(config('ai.providers', []))
            ->flatMap(fn (array $provider): array => $provider['models'] ?? [])
            ->unique()
            ->values()
            ->all();
    }
}
