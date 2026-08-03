<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable([
    'tenant_id',
    'user_id',
    'is_active',
    'hides_other_conversations',
    'signs_messages',
    'work_days',
    'work_starts_at',
    'work_ends_at',
    'blocked_until',
])]
class TenantMembership extends Pivot
{
    use HasUuids;

    public $incrementing = false;

    protected $table = 'tenant_user';

    protected $keyType = 'string';

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isWithinWorkSchedule(): bool
    {
        if ($this->blocked_until !== null && $this->blocked_until->isFuture()) {
            return false;
        }

        $days = $this->work_days ?? [];

        if ($days !== [] && ! in_array(now()->dayOfWeek, array_map('intval', $days), true)) {
            return false;
        }

        if ($this->work_starts_at === null || $this->work_ends_at === null) {
            return true;
        }

        $now = now()->format('H:i:s');

        return $now >= $this->work_starts_at && $now <= $this->work_ends_at;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'hides_other_conversations' => 'boolean',
            'signs_messages' => 'boolean',
            'work_days' => 'array',
            'blocked_until' => 'datetime',
        ];
    }
}
