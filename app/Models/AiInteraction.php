<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'tenant_id',
    'conversation_id',
    'ai_objective_id',
    'provider',
    'model',
    'status',
    'input_tokens',
    'output_tokens',
    'cost_micro_cents',
    'latency_ms',
    'error',
    'metadata',
])]
class AiInteraction extends Model
{
    use BelongsToTenant, HasFactory, HasUuids;

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function objective(): BelongsTo
    {
        return $this->belongsTo(AiObjective::class, 'ai_objective_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }
}
