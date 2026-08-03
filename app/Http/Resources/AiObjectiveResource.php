<?php

namespace App\Http\Resources;

use App\Models\AiObjective;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AiObjective
 */
class AiObjectiveResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'provider' => $this->provider,
            'model' => $this->model,
            'temperature' => $this->temperature,
            'max_tokens' => $this->max_tokens,
            'system_prompt' => $this->system_prompt,
            'tools' => $this->tools ?? [],
            'cost_limit_cents' => $this->cost_limit_cents,
            'max_turns' => $this->max_turns,
            'handoff_service_queue_id' => $this->handoff_service_queue_id,
            'closing_condition' => $this->closing_condition,
            'is_active' => $this->is_active,
            'spent_cents' => $this->when($request->routeIs('ai-objectives.*'), fn (): int => $this->spentCents()),
        ];
    }
}
