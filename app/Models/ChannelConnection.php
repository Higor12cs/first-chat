<?php

namespace App\Models;

use App\Domain\Messaging\DataObjects\ConnectorDefinition;
use App\Domain\Messaging\Enums\Channel;
use App\Domain\Messaging\Enums\ConnectionStatus;
use App\Models\Concerns\BelongsToTenant;
use App\Services\Messaging\ConnectorRegistry;
use App\Support\PublicUrl;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'tenant_id',
    'name',
    'driver',
    'channel',
    'status',
    'credentials',
    'settings',
    'external_identifier',
    'qr_code',
    'pair_code',
    'last_error',
    'last_connected_at',
    'default_service_queue_id',
    'chat_flow_id',
    'is_active',
])]
#[Hidden(['credentials'])]
class ChannelConnection extends Model
{
    use BelongsToTenant, HasFactory, HasUuids;

    public function definition(): ConnectorDefinition
    {
        return app(ConnectorRegistry::class)->definition($this->driver);
    }

    public function defaultServiceQueue(): BelongsTo
    {
        return $this->belongsTo(ServiceQueue::class, 'default_service_queue_id');
    }

    public function chatFlow(): BelongsTo
    {
        return $this->belongsTo(ChatFlow::class);
    }

    public function contactChannels(): HasMany
    {
        return $this->hasMany(ContactChannel::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function credential(string $key, mixed $default = null): mixed
    {
        return data_get($this->credentials, $key, $default);
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    public function webhookUrl(): string
    {
        return PublicUrl::to(route('webhooks.connector', ['connection' => $this->id], absolute: false));
    }

    public function instanceName(): string
    {
        return collect([
            Str::slug((string) config('connectors.instance_prefix')),
            Str::slug((string) $this->tenant?->name),
            Str::lower(Str::random(8)),
        ])->filter()->implode('-');
    }

    public function scopeConnected(Builder $query): Builder
    {
        return $query->where('status', ConnectionStatus::Connected->value)->where('is_active', true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => Channel::class,
            'status' => ConnectionStatus::class,
            'credentials' => 'encrypted:array',
            'settings' => 'array',
            'is_active' => 'boolean',
            'last_connected_at' => 'datetime',
        ];
    }
}
