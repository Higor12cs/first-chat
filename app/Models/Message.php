<?php

namespace App\Models;

use App\Domain\Conversations\Enums\MessageSource;
use App\Domain\Messaging\Enums\MessageDirection;
use App\Domain\Messaging\Enums\MessageStatus;
use App\Domain\Messaging\Enums\MessageType;
use App\Models\Concerns\BelongsToTenant;
use App\Support\PublicUrl;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

#[Fillable([
    'tenant_id',
    'conversation_id',
    'user_id',
    'reply_to_message_id',
    'external_id',
    'direction',
    'type',
    'status',
    'source',
    'is_internal',
    'body',
    'media_url',
    'media_name',
    'media_mime_type',
    'transcription',
    'media_size',
    'error',
    'metadata',
    'sent_at',
    'delivered_at',
    'read_at',
])]
class Message extends Model
{
    use BelongsToTenant, HasFactory, HasUuids;

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reply_to_message_id');
    }

    public function isInbound(): bool
    {
        return $this->direction === MessageDirection::Inbound;
    }

    public function isWhisper(): bool
    {
        return (bool) $this->is_internal;
    }

    public function mediaNeedsProxy(): bool
    {
        return filled($this->media_url) && ! $this->servesMediaItself();
    }

    public function mediaUrlForBrowser(): ?string
    {
        if (blank($this->media_url)) {
            return null;
        }

        return $this->mediaNeedsProxy()
            ? route('conversations.messages.media', ['conversation' => $this->conversation_id, 'message' => $this->id])
            : $this->media_url;
    }

    public function mediaIsStored(): bool
    {
        return filled($this->media_url) && ! Str::startsWith($this->media_url, ['http://', 'https://']);
    }

    public function mediaUrlForProvider(): ?string
    {
        if (! $this->mediaIsStored()) {
            return $this->media_url;
        }

        return PublicUrl::to(URL::temporarySignedRoute(
            'messages.media.signed',
            now()->addMinutes(30),
            ['message' => $this->id],
            absolute: false,
        ));
    }

    private function servesMediaItself(): bool
    {
        $host = parse_url((string) $this->media_url, PHP_URL_HOST);

        return $host !== null && $host === parse_url((string) config('app.url'), PHP_URL_HOST);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'direction' => MessageDirection::class,
            'type' => MessageType::class,
            'status' => MessageStatus::class,
            'source' => MessageSource::class,
            'is_internal' => 'boolean',
            'metadata' => 'array',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }
}
