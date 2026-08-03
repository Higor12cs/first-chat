<?php

namespace App\Models;

use App\Domain\Tenancy\TenantContext;
use App\Models\Concerns\HasTenantMemberships;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

#[Fillable([
    'name',
    'email',
    'password',
    'avatar_url',
    'phone',
    'is_super_admin',
    'is_active',
    'auto_lock_minutes',
])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasFactory, HasTenantMemberships, HasUuids, Notifiable;

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function serviceQueues(): BelongsToMany
    {
        return $this->belongsToMany(ServiceQueue::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class, 'assigned_user_id');
    }

    /**
     * @return Collection<int, string>
     */
    public function permissions(): Collection
    {
        return once(fn (): Collection => $this->roles()
            ->get()
            ->flatMap(fn (Role $role): Collection => $role->permissions())
            ->unique()
            ->values());
    }

    public function hasPermission(string $permission): bool
    {
        return $this->is_super_admin || $this->permissions()->contains($permission);
    }

    public function isWithinWorkSchedule(): bool
    {
        return $this->membership()?->isWithinWorkSchedule() ?? true;
    }

    public function canAccessPlatform(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        $membership = $this->membership();

        if ($membership === null) {
            return app(TenantContext::class)->id() === null || $this->is_super_admin;
        }

        return $membership->is_active && $membership->isWithinWorkSchedule();
    }

    public function scopeActive(Builder $query): Builder
    {
        $tenantId = app(TenantContext::class)->id();

        return $query
            ->where('is_active', true)
            ->when($tenantId !== null, fn (Builder $inner): Builder => $inner->whereHas(
                'memberships',
                fn (Builder $membership) => $membership->where('tenant_id', $tenantId)->where('is_active', true)
            ));
    }

    protected function hidesOtherConversations(): Attribute
    {
        return Attribute::get(fn (): bool => (bool) $this->membership()?->hides_other_conversations);
    }

    protected function signsMessages(): Attribute
    {
        return Attribute::get(fn (): ?bool => $this->membership()?->signs_messages);
    }

    protected function workDays(): Attribute
    {
        return Attribute::get(fn (): ?array => $this->membership()?->work_days);
    }

    protected function workStartsAt(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->membership()?->work_starts_at);
    }

    protected function workEndsAt(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->membership()?->work_ends_at);
    }

    protected function blockedUntil(): Attribute
    {
        return Attribute::get(fn () => $this->membership()?->blocked_until);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'is_active' => 'boolean',
            'locked_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }
}
