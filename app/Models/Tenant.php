<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'slug',
    'document',
    'status',
    'timezone',
    'trial_ends_at',
    'access_expires_at',
    'suspended_at',
    'settings',
    'price_cents',
    'max_users',
    'max_connections',
    'max_monthly_messages',
    'max_monthly_ai_cost_cents',
])]
class Tenant extends Model
{
    use HasFactory, HasUuids;

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(TenantMembership::class)
            ->withPivot([
                'id',
                'is_active',
                'hides_other_conversations',
                'signs_messages',
                'work_days',
                'work_starts_at',
                'work_ends_at',
                'blocked_until',
            ])
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TenantMembership::class);
    }

    public function connections(): HasMany
    {
        return $this->hasMany(ChannelConnection::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function isActive(): bool
    {
        return $this->status === 'active' && $this->suspended_at === null;
    }

    public function hasValidAccess(): bool
    {
        return $this->access_expires_at === null || $this->access_expires_at->endOfDay()->isFuture();
    }

    public function isReachableBy(User $user): bool
    {
        return $this->isActive() && ($this->hasValidAccess() || $user->is_super_admin);
    }

    public function limit(string $key): ?int
    {
        $value = $this->getAttribute($key);

        return $value === null ? null : (int) $value;
    }

    public function priceCents(): int
    {
        return (int) $this->price_cents;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'trial_ends_at' => 'datetime',
            'access_expires_at' => 'date',
            'suspended_at' => 'datetime',
        ];
    }
}
