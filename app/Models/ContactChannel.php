<?php

namespace App\Models;

use App\Domain\Messaging\Enums\Channel;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'tenant_id',
    'contact_id',
    'channel_connection_id',
    'channel',
    'identifier',
    'display_name',
    'avatar_url',
    'is_group',
    'metadata',
])]
class ContactChannel extends Model
{
    use BelongsToTenant, HasFactory, HasUuids;

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(ChannelConnection::class, 'channel_connection_id');
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => Channel::class,
            'is_group' => 'boolean',
            'metadata' => 'array',
        ];
    }
}
