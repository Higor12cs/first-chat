<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

#[Fillable([
    'tenant_id',
    'name',
    'slug',
    'description',
    'color',
    'icon',
    'priority',
    'assignment_strategy',
    'business_hours',
    'outside_hours_message',
    'ai_objective_id',
    'is_default',
    'is_active',
])]
class ServiceQueue extends Model
{
    use BelongsToTenant, HasFactory, HasUuids;

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function aiObjective(): BelongsTo
    {
        return $this->belongsTo(AiObjective::class);
    }

    public function isOpenAt(?Carbon $moment = null): bool
    {
        $moment ??= now();
        $hours = $this->business_hours ?? [];

        if ($hours === []) {
            return true;
        }

        $today = data_get($hours, (string) $moment->dayOfWeek);

        if (! is_array($today) || blank(data_get($today, 'start')) || blank(data_get($today, 'end'))) {
            return false;
        }

        $time = $moment->format('H:i');

        return $time >= data_get($today, 'start') && $time <= data_get($today, 'end');
    }

    public static function default(): ?self
    {
        return static::query()->where('is_default', true)->where('is_active', true)->ordered()->first()
            ?? static::query()->where('is_active', true)->ordered()->first();
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderByDesc('priority')->orderBy('name');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'business_hours' => 'array',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
