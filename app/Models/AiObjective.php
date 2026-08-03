<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Services\Ai\AiCostCalculator;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'name',
    'slug',
    'description',
    'provider',
    'model',
    'temperature',
    'max_tokens',
    'system_prompt',
    'tools',
    'cost_limit_cents',
    'max_turns',
    'handoff_service_queue_id',
    'closing_condition',
    'is_active',
])]
class AiObjective extends Model
{
    use BelongsToTenant, HasFactory, HasUuids;

    public function handoffServiceQueue(): BelongsTo
    {
        return $this->belongsTo(ServiceQueue::class, 'handoff_service_queue_id');
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(AiInteraction::class);
    }

    public function spentMicroCents(): int
    {
        return (int) $this->interactions()->sum('cost_micro_cents');
    }

    public function hasBudgetLeft(): bool
    {
        return $this->cost_limit_cents === null
            || $this->spentMicroCents() < $this->cost_limit_cents * AiCostCalculator::MICRO_CENTS_PER_CENT;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'temperature' => 'float',
            'tools' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
