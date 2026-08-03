<?php

namespace App\Models;

use App\Domain\Conversations\Enums\ConversationSection;
use App\Domain\Conversations\Enums\ConversationStatus;
use App\Domain\Messaging\Enums\Channel;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

#[Fillable([
    'tenant_id',
    'contact_id',
    'contact_channel_id',
    'channel_connection_id',
    'service_queue_id',
    'assigned_user_id',
    'closed_by_user_id',
    'ai_objective_id',
    'chat_flow_id',
    'channel',
    'status',
    'subject',
    'is_group',
    'priority',
    'unread_count',
    'flow_state',
    'metadata',
    'last_message_at',
    'last_inbound_at',
    'first_response_at',
    'closed_at',
    'no_action_at',
])]
class Conversation extends Model
{
    use BelongsToTenant, HasFactory, HasUuids;

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function contactChannel(): BelongsTo
    {
        return $this->belongsTo(ContactChannel::class);
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(ChannelConnection::class, 'channel_connection_id');
    }

    public function serviceQueue(): BelongsTo
    {
        return $this->belongsTo(ServiceQueue::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function aiObjective(): BelongsTo
    {
        return $this->belongsTo(AiObjective::class);
    }

    public function chatFlow(): BelongsTo
    {
        return $this->belongsTo(ChatFlow::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function lastMessage(): HasOne
    {
        return $this->hasOne(Message::class)->latestOfMany();
    }

    public function notes(): HasMany
    {
        return $this->hasMany(ConversationNote::class);
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable');
    }

    public function isOpen(): bool
    {
        return $this->status->isActive();
    }

    public function section(): ConversationSection
    {
        return $this->status->section((bool) $this->is_group);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '!=', ConversationStatus::Closed->value);
    }

    public function scopeInSection(Builder $query, ConversationSection $section): Builder
    {
        return $section->apply($query);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasPermission('conversations.view-all') && ! $user->hides_other_conversations) {
            return $query;
        }

        return $query->where(function (Builder $query) use ($user): void {
            $query->where('is_group', true)
                ->orWhere('assigned_user_id', $user->id)
                ->orWhere(function (Builder $query) use ($user): void {
                    $query->whereNull('assigned_user_id')
                        ->whereIn('service_queue_id', $user->serviceQueues()->pluck('service_queues.id'));
                });
        });
    }

    public function scopePrivate(Builder $query): Builder
    {
        return $query->where('is_group', false);
    }

    public function scopeGroups(Builder $query): Builder
    {
        return $query->where('is_group', true);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => Channel::class,
            'status' => ConversationStatus::class,
            'is_group' => 'boolean',
            'flow_state' => 'array',
            'metadata' => 'array',
            'last_message_at' => 'datetime',
            'last_inbound_at' => 'datetime',
            'first_response_at' => 'datetime',
            'closed_at' => 'datetime',
            'no_action_at' => 'datetime',
        ];
    }
}
