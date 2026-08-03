<?php

use App\Domain\Conversations\Enums\ConversationStatus;
use App\Domain\Messaging\Enums\MessageDirection;
use App\Domain\Messaging\Enums\MessageStatus;
use App\Domain\Messaging\Enums\MessageType;
use App\Events\Conversations\MessageStatusUpdated;
use App\Http\Resources\MessageResource;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    $this->tenant = tenant();
    $this->user = userFor($this->tenant);
    $this->conversation = Conversation::factory()->create(['status' => ConversationStatus::Open]);
});

function remoteMedia(Conversation $conversation, array $attributes = []): Message
{
    return Message::factory()->create([
        'conversation_id' => $conversation->id,
        'direction' => MessageDirection::Inbound,
        'type' => MessageType::Audio,
        'media_url' => 'https://midia.uazapi.com/files/abc.mp3',
        'media_mime_type' => 'audio/mpeg',
        'media_name' => 'audio.mp3',
        ...$attributes,
    ]);
}

it('never hands the provider address to the browser', function (): void {
    $message = remoteMedia($this->conversation);

    $payload = MessageResource::make($message)->resolve();

    expect($payload['media_url'])->not->toContain('uazapi')
        ->and($payload['media_url'])->toBe(route('conversations.messages.media', [
            'conversation' => $this->conversation->id,
            'message' => $message->id,
        ]));
});

it('keeps the provider address stored for the server to use', function (): void {
    $message = remoteMedia($this->conversation);

    expect($message->fresh()->media_url)->toBe('https://midia.uazapi.com/files/abc.mp3');
});

it('serves the attachment through our own address', function (): void {
    Http::fake(['midia.uazapi.com/*' => Http::response('conteudo-do-audio', 200)]);

    $message = remoteMedia($this->conversation);

    $response = $this->actingAs($this->user)->get(route('conversations.messages.media', [
        'conversation' => $this->conversation->id,
        'message' => $message->id,
    ]));

    $response->assertOk()->assertHeader('Content-Type', 'audio/mpeg');

    expect($response->streamedContent())->toBe('conteudo-do-audio');
});

it('leaves a legacy public attachment on its own address', function (): void {
    $message = remoteMedia($this->conversation, [
        'direction' => MessageDirection::Outbound,
        'media_url' => config('app.url').'/storage/attachments/nota.pdf',
    ]);

    expect(MessageResource::make($message)->resolve()['media_url'])
        ->toBe(config('app.url').'/storage/attachments/nota.pdf');
});

it('serves an attachment the agent sent through the authorized route', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('attachments/nota.pdf', 'conteudo-do-anexo');

    $message = remoteMedia($this->conversation, [
        'direction' => MessageDirection::Outbound,
        'type' => MessageType::Document,
        'media_url' => 'attachments/nota.pdf',
        'media_name' => 'nota.pdf',
        'media_mime_type' => 'application/pdf',
    ]);

    expect(MessageResource::make($message)->resolve()['media_url'])
        ->toBe(route('conversations.messages.media', [
            'conversation' => $this->conversation->id,
            'message' => $message->id,
        ]));

    $this->actingAs($this->user)->get(route('conversations.messages.media', [
        'conversation' => $this->conversation->id,
        'message' => $message->id,
    ]))->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

it('refuses an agent attachment to whoever is not signed in', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('attachments/nota.pdf', 'conteudo-do-anexo');

    $message = remoteMedia($this->conversation, [
        'direction' => MessageDirection::Outbound,
        'media_url' => 'attachments/nota.pdf',
    ]);

    $this->get(route('conversations.messages.media', [
        'conversation' => $this->conversation->id,
        'message' => $message->id,
    ]))->assertRedirect(route('login'));
});

it('lets the provider fetch the attachment only through a signed address', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('attachments/nota.pdf', 'conteudo-do-anexo');

    $message = remoteMedia($this->conversation, [
        'direction' => MessageDirection::Outbound,
        'media_url' => 'attachments/nota.pdf',
        'media_mime_type' => 'application/pdf',
    ]);

    $signed = $message->mediaUrlForProvider();

    expect($signed)->toContain('signature=');

    $this->get(parse_url($signed, PHP_URL_PATH).'?'.parse_url($signed, PHP_URL_QUERY))->assertOk();

    $this->get(route('messages.media.signed', ['message' => $message->id]))->assertForbidden();
});

it('refuses an attachment from another conversation', function (): void {
    $other = Conversation::factory()->create(['status' => ConversationStatus::Open]);
    $message = remoteMedia($other);

    $this->actingAs($this->user)->get(route('conversations.messages.media', [
        'conversation' => $this->conversation->id,
        'message' => $message->id,
    ]))->assertNotFound();
});

it('keeps the failure reason out of the message payload', function (): void {
    $message = Message::factory()->create([
        'conversation_id' => $this->conversation->id,
        'status' => MessageStatus::Failed,
        'error' => 'Falha na comunicação com o conector [uazapi]: instance not found',
    ]);

    $payload = MessageResource::make($message)->resolve();
    $broadcast = (new MessageStatusUpdated($message))->broadcastWith();

    expect($payload)->not->toHaveKey('error')
        ->and($broadcast)->not->toHaveKey('error')
        ->and(json_encode($payload).json_encode($broadcast))->not->toContain('uazapi');
});

it('still records the failure reason for support', function (): void {
    $message = Message::factory()->create([
        'conversation_id' => $this->conversation->id,
        'status' => MessageStatus::Failed,
        'error' => 'Falha na comunicação com o conector [uazapi]: instance not found',
    ]);

    expect($message->fresh()->error)->toContain('instance not found');
});
