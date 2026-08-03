<?php

namespace App\Http\Resources;

use App\Models\ChatFlow;
use App\Services\Chatbot\FlowNodeRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ChatFlow
 */
class ChatFlowSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_active' => $this->is_active,
            'levels' => $this->levels(),
        ];
    }

    /**
     * @return array<int, array{id: string, label: string}>
     */
    private function levels(): array
    {
        $labels = collect(app(FlowNodeRegistry::class)->options())->pluck('label', 'value');

        return collect($this->nodes ?? [])
            ->map(fn (array $node): array => [
                'id' => (string) data_get($node, 'id'),
                'label' => (string) (data_get($node, 'data.label')
                    ?: $labels->get((string) data_get($node, 'type'), 'Bloco')),
            ])
            ->values()
            ->all();
    }
}
