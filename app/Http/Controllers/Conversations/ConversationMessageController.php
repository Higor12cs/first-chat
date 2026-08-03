<?php

namespace App\Http\Controllers\Conversations;

use App\Actions\Messaging\SendMessage;
use App\Domain\Conversations\Enums\MessageSource;
use App\Domain\Messaging\Enums\MessageStatus;
use App\Domain\Messaging\Enums\MessageType;
use App\Events\Conversations\ConversationUpdated;
use App\Events\Conversations\MessageStatusUpdated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Conversations\SendMessageRequest;
use App\Http\Resources\MessageResource;
use App\Jobs\Messaging\DeliverMessage;
use App\Jobs\Messaging\RevokeMessage;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ConversationMessageController extends Controller
{
    public function store(SendMessageRequest $request, Conversation $conversation, SendMessage $sendMessage): JsonResponse
    {
        $media = $request->file('media');

        $path = $media?->store('attachments', 'local');

        $message = $sendMessage->handle(
            conversation: $conversation,
            body: $request->string('body')->value() ?: null,
            source: MessageSource::Agent,
            user: $request->user(),
            type: $media === null ? MessageType::Text : $this->typeFor($media->getMimeType()),
            mediaUrl: $path,
            mediaName: $media?->getClientOriginalName(),
            mediaMimeType: $media?->getMimeType(),
            replyTo: $request->filled('reply_to_message_id')
                ? Message::find($request->string('reply_to_message_id'))
                : null,
            internal: $request->boolean('internal'),
            sign: $request->has('sign') ? $request->boolean('sign') : null,
        );

        return response()->json([
            'message' => MessageResource::make($message->load(['user', 'replyTo.user']))->resolve(),
        ], 201);
    }

    public function media(Conversation $conversation, Message $message): StreamedResponse
    {
        abort_unless($message->conversation_id === $conversation->id, 404);
        abort_unless($message->mediaNeedsProxy(), 404);

        $disk = Storage::disk('local');

        if ($message->mediaIsStored()) {
            abort_unless($disk->exists((string) $message->media_url), 404);

            return $disk->response((string) $message->media_url, $message->media_name, [
                'Content-Type' => $message->media_mime_type ?: 'application/octet-stream',
                'Cache-Control' => 'private, max-age=3600',
            ]);
        }

        $response = Http::timeout(30)->get((string) $message->media_url);

        abort_if($response->failed(), 404);

        return response()->streamDownload(
            function () use ($response): void {
                echo $response->body();
            },
            $message->media_name ?: 'anexo',
            [
                'Content-Type' => $message->media_mime_type ?: 'application/octet-stream',
                'Cache-Control' => 'private, max-age=3600',
            ],
            'inline',
        );
    }

    public function resend(Conversation $conversation, Message $message): JsonResponse
    {
        abort_unless($message->conversation_id === $conversation->id, 404);
        abort_if($message->isWhisper(), 422, 'Um sussurro não é entregue ao contato.');

        $message->forceFill([
            'status' => MessageStatus::Pending,
            'error' => null,
            'sent_at' => now(),
        ])->save();

        DeliverMessage::dispatch($message);

        return response()->json([
            'message' => MessageResource::make($message->load(['user', 'replyTo.user']))->resolve(),
        ]);
    }

    public function cancel(Conversation $conversation, Message $message): JsonResponse
    {
        abort_unless($message->conversation_id === $conversation->id, 404);
        abort_unless($message->status->isOpen(), 422, 'Esta mensagem já saiu para o contato.');

        $message->forceFill(['status' => MessageStatus::Canceled, 'error' => null])->save();

        MessageStatusUpdated::dispatch($message);

        return response()->json([
            'message' => MessageResource::make($message->load(['user', 'replyTo.user']))->resolve(),
        ]);
    }

    public function destroy(Conversation $conversation, Message $message): JsonResponse
    {
        abort_unless($message->conversation_id === $conversation->id, 404);
        abort_if($message->isInbound(), 422, 'A mensagem do contato não pode ser excluída.');

        $message->forceFill(['status' => MessageStatus::Deleted, 'error' => null])->save();

        if (filled($message->external_id)) {
            RevokeMessage::dispatch($message);
        }

        MessageStatusUpdated::dispatch($message);

        ConversationUpdated::dispatch($conversation);

        return response()->json([
            'message' => MessageResource::make($message->load(['user', 'replyTo.user']))->resolve(),
        ]);
    }

    private function typeFor(?string $mimeType): MessageType
    {
        return match (true) {
            str_starts_with((string) $mimeType, 'image/') => MessageType::Image,
            str_starts_with((string) $mimeType, 'video/') => MessageType::Video,
            str_starts_with((string) $mimeType, 'audio/') => MessageType::Audio,
            default => MessageType::Document,
        };
    }
}
