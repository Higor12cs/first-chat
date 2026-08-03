<?php

namespace App\Domain\Messaging\Connectors\Uazapi;

use App\Domain\Messaging\Connectors\BaseConnector;
use App\Domain\Messaging\Contracts\ConnectorEvent;
use App\Domain\Messaging\Contracts\DeletesMessages;
use App\Domain\Messaging\Contracts\DownloadsMedia;
use App\Domain\Messaging\Contracts\HandlesWebhooks;
use App\Domain\Messaging\Contracts\ListsGroups;
use App\Domain\Messaging\Contracts\ManagesSession;
use App\Domain\Messaging\Contracts\ProvisionsInstance;
use App\Domain\Messaging\Contracts\SupportsPairingCode;
use App\Domain\Messaging\Contracts\SupportsPresence;
use App\Domain\Messaging\DataObjects\ConnectionStatusUpdate;
use App\Domain\Messaging\DataObjects\ContactIdentity;
use App\Domain\Messaging\DataObjects\DeliveryStatusUpdate;
use App\Domain\Messaging\DataObjects\InboundMessage;
use App\Domain\Messaging\DataObjects\InstanceProvisioning;
use App\Domain\Messaging\DataObjects\MediaFile;
use App\Domain\Messaging\DataObjects\MessageResult;
use App\Domain\Messaging\DataObjects\OutgoingMessage;
use App\Domain\Messaging\Enums\ConnectionStatus;
use App\Domain\Messaging\Enums\MessageStatus;
use App\Domain\Messaging\Enums\MessageType;
use App\Domain\Messaging\Exceptions\ConnectorException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class UazapiConnector extends BaseConnector implements DeletesMessages, DownloadsMedia, HandlesWebhooks, ListsGroups, ManagesSession, ProvisionsInstance, SupportsPairingCode, SupportsPresence
{
    public function send(OutgoingMessage $message): MessageResult
    {
        $response = match (true) {
            $message->type === MessageType::Interactive => $this->post('/send/menu', [
                'number' => $message->recipient,
                'type' => 'button',
                'text' => $message->body,
                'choices' => array_map(fn (array $button): string => $button['label'], $message->buttons),
            ]),
            $message->type->isMedia() => $this->post('/send/media', [
                'number' => $message->recipient,
                'type' => $message->type->value,
                'file' => $message->mediaUrl,
                'docName' => $message->mediaName,
                'text' => $message->body,
                'replyid' => $message->replyToExternalId,
            ]),
            default => $this->post('/send/text', [
                'number' => $message->recipient,
                'text' => $message->body,
                'replyid' => $message->replyToExternalId,
            ]),
        };

        if ($response->failed()) {
            return MessageResult::failed(
                $response->json('error') ?? $response->body(),
                retryable: $response->serverError() || $response->status() === 429,
            );
        }

        return new MessageResult(
            externalId: $response->json('messageid') ?? $response->json('id'),
            status: MessageStatus::Sent,
            raw: $response->json() ?? [],
        );
    }

    public function isProvisioned(): bool
    {
        return filled($this->credential('token'));
    }

    public function provision(): InstanceProvisioning
    {
        $name = $this->connection->instanceName();

        $response = Http::baseUrl($this->baseUrl())
            ->withHeaders(['admintoken' => (string) $this->credential('admin_token')])
            ->acceptJson()
            ->timeout(30)
            ->post('/instance/create', [
                'name' => $name,
                'adminField01' => (string) config('connectors.instance_prefix'),
                'adminField02' => now()->format('Y-m-d H:i:s'),
            ]);

        if ($response->failed()) {
            throw ConnectorException::requestFailed($this->driver(), $response->body());
        }

        $token = (string) ($response->json('token') ?? $response->json('instance.token'));
        $id = (string) ($response->json('instance.id') ?? $response->json('id'));

        $this->registerWebhook($token);

        return new InstanceProvisioning(
            credentials: ['token' => $token, 'instance_id' => $id, 'instance_name' => $name],
        );
    }

    private function registerWebhook(string $token): void
    {
        $response = Http::baseUrl($this->baseUrl())
            ->withHeaders(['token' => $token])
            ->acceptJson()
            ->timeout(30)
            ->post('/webhook', [
                'enabled' => true,
                'url' => $this->connection->webhookUrl(),
                'events' => ['messages', 'messages_update', 'connection'],
                'excludeMessages' => ['wasSentByApi'],
            ]);

        if ($response->failed()) {
            Log::warning('Could not register the instance webhook.', [
                'connection_id' => $this->connection->id,
                'driver' => $this->driver(),
                'response' => $response->body(),
            ]);
        }
    }

    public function connect(): ConnectionStatusUpdate
    {
        return $this->startSession([]);
    }

    public function pairWithPhone(string $phone): ConnectionStatusUpdate
    {
        return $this->startSession(['phone' => $phone]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function startSession(array $payload): ConnectionStatusUpdate
    {
        $response = $this->post('/instance/connect', $payload);

        if ($response->failed()) {
            throw ConnectorException::requestFailed($this->driver(), $response->body());
        }

        return $this->toStatusUpdate($response->json('instance', []));
    }

    public function disconnect(): ConnectionStatusUpdate
    {
        $this->post('/instance/disconnect');

        return new ConnectionStatusUpdate(status: ConnectionStatus::Disconnected);
    }

    public function status(): ConnectionStatusUpdate
    {
        $response = $this->request()->get('/instance/status');

        if ($response->failed()) {
            return new ConnectionStatusUpdate(
                status: ConnectionStatus::Failed,
                metadata: array_filter([
                    'error' => $response->body(),
                    'token_rejected' => $response->unauthorized() || $response->forbidden(),
                ]),
            );
        }

        return $this->toStatusUpdate($response->json('instance', []));
    }

    /**
     * @return array<int, ContactIdentity>
     */
    public function listGroups(): array
    {
        $response = $this->request()->get('/group/list', ['noparticipants' => 'true']);

        if ($response->failed()) {
            throw ConnectorException::requestFailed($this->driver(), $response->body());
        }

        return collect($response->json('groups', []))
            ->filter(fn (array $group): bool => filled(data_get($group, 'JID')))
            ->map(fn (array $group): ContactIdentity => new ContactIdentity(
                identifier: (string) data_get($group, 'JID'),
                name: data_get($group, 'Name') ?: null,
                isGroup: true,
            ))
            ->values()
            ->all();
    }

    public function sendTyping(string $recipient, bool $typing = true): void
    {
        $response = $this->post('/message/presence', [
            'number' => $recipient,
            'presence' => $typing ? 'composing' : 'paused',
            'delay' => $typing ? 10000 : null,
        ]);

        $this->warnOnFailure($response, 'Não foi possível enviar a presença.');
    }

    public function deleteMessage(string $externalId): void
    {
        $response = $this->post('/message/delete', ['id' => $externalId]);

        if ($response->failed()) {
            throw ConnectorException::requestFailed($this->driver(), $response->body());
        }
    }

    public function downloadMedia(string $externalId): ?MediaFile
    {
        $response = $this->post('/message/download', ['id' => $externalId]);

        if ($response->failed()) {
            $this->warnOnFailure($response, 'Não foi possível baixar a mídia da mensagem.');

            return null;
        }

        $url = $response->json('fileURL');

        return blank($url) ? null : new MediaFile(
            url: (string) $url,
            mimeType: $response->json('mimetype'),
            name: $response->json('fileName'),
        );
    }

    public function markAsRead(string $recipient, string ...$externalIds): void
    {
        if ($externalIds === []) {
            return;
        }

        $response = $this->post('/message/markread', ['id' => array_values($externalIds)]);

        $this->warnOnFailure($response, 'Não foi possível confirmar a leitura.');
    }

    private function warnOnFailure(Response $response, string $message): void
    {
        if ($response->failed()) {
            Log::warning($message, [
                'connection_id' => $this->connection->id,
                'driver' => $this->driver(),
                'status' => $response->status(),
                'response' => $response->body(),
            ]);
        }
    }

    public function verifyWebhook(Request $request): bool
    {
        $received = (string) ($request->input('token') ?? $request->header('token', ''));

        if (filled($received)) {
            $token = (string) $this->credential('token');

            return filled($token) && hash_equals($token, $received);
        }

        $secret = (string) config('connectors.webhook_secret');

        return filled($secret) && hash_equals($secret, (string) $request->header('X-Webhook-Secret', ''));
    }

    /**
     * @return array<int, ConnectorEvent>
     */
    public function parseWebhook(Request $request): array
    {
        return match ($request->input('EventType', $request->input('event'))) {
            'messages', 'message' => $this->toInboundMessages($request->input('messages', array_filter([$request->input('message')]))),
            'messages_update' => $this->parseStatuses($request),
            'connection', 'status' => [$this->toStatusUpdate($request->input('instance', []))],
            default => [],
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $messages
     * @return array<int, InboundMessage>
     */
    private function toInboundMessages(array $messages): array
    {
        return collect($messages)
            ->map(fn (array $message): InboundMessage => new InboundMessage(
                externalId: (string) (data_get($message, 'messageid') ?? data_get($message, 'id')),
                contact: $this->toContactIdentity($message),
                type: $this->toMessageType((string) data_get($message, 'messageType', 'text')),
                body: data_get($message, 'text') ?? data_get($message, 'caption'),
                mediaUrl: data_get($message, 'fileURL') ?? data_get($message, 'mediaUrl'),
                mediaName: data_get($message, 'fileName') ?? data_get($message, 'content.fileName'),
                mediaMimeType: data_get($message, 'mimetype') ?? data_get($message, 'content.mimetype'),
                replyToExternalId: data_get($message, 'quoted'),
                sentAt: $this->toCarbon(data_get($message, 'messageTimestamp')),
                fromMe: (bool) data_get($message, 'fromMe', false),
                raw: $message,
            ))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $message
     */
    private function toContactIdentity(array $message): ContactIdentity
    {
        $chatId = (string) (data_get($message, 'chatid') ?? data_get($message, 'sender'));
        $fromMe = (bool) data_get($message, 'fromMe', false);
        $isGroup = (bool) data_get($message, 'isGroup', str_contains($chatId, '@g.us'));
        $sender = (string) (data_get($message, 'sender') ?? '');

        return new ContactIdentity(
            identifier: $chatId,
            name: $fromMe ? null : data_get($message, 'senderName') ?? data_get($message, 'pushName'),
            avatarUrl: $fromMe ? null : data_get($message, 'senderProfilePicture'),
            phone: $this->phoneFrom($isGroup ? ($fromMe ? '' : $sender) : $chatId),
            isGroup: $isGroup,
        );
    }

    /**
     * @return array<int, DeliveryStatusUpdate>
     */
    private function parseStatuses(Request $request): array
    {
        $receipt = $request->input('event');

        return is_array($receipt)
            ? $this->fromReceipt($receipt, $request->input('state'))
            : $this->fromStatusList($request->input('messages', []));
    }

    /**
     * @param  array<string, mixed>  $receipt
     * @return array<int, DeliveryStatusUpdate>
     */
    private function fromReceipt(array $receipt, mixed $state): array
    {
        $status = $this->toMessageStatus(data_get($receipt, 'Type') ?? $state);
        $happenedAt = $this->toCarbon(data_get($receipt, 'Timestamp'));

        return collect((array) (data_get($receipt, 'MessageIDs') ?? data_get($receipt, 'messageIDs') ?? []))
            ->filter(fn (mixed $id): bool => filled($id))
            ->map(fn (mixed $id): DeliveryStatusUpdate => new DeliveryStatusUpdate(
                externalId: (string) $id,
                status: $status,
                happenedAt: $happenedAt,
            ))
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $statuses
     * @return array<int, DeliveryStatusUpdate>
     */
    private function fromStatusList(array $statuses): array
    {
        return collect($statuses)
            ->map(fn (array $status): DeliveryStatusUpdate => new DeliveryStatusUpdate(
                externalId: (string) (data_get($status, 'messageid') ?? data_get($status, 'id')),
                status: $this->toMessageStatus(data_get($status, 'status')),
                happenedAt: $this->toCarbon(data_get($status, 'timestamp')),
            ))
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $instance
     */
    private function toStatusUpdate(array $instance): ConnectionStatusUpdate
    {
        $status = match (strtolower((string) data_get($instance, 'status'))) {
            'connected' => ConnectionStatus::Connected,
            'connecting' => ConnectionStatus::Connecting,
            'hibernated' => ConnectionStatus::Disconnected,
            default => ConnectionStatus::Disconnected,
        };

        return new ConnectionStatusUpdate(
            status: $status,
            qrCode: $this->optionalString($instance, 'qrcode'),
            pairCode: $this->optionalString($instance, 'paircode'),
            externalIdentifier: $this->optionalString($instance, 'owner') ?? $this->optionalString($instance, 'profileName'),
            metadata: array_filter([
                'profile_name' => $this->optionalString($instance, 'profileName'),
            ]),
        );
    }

    /**
     * @param  array<string, mixed>  $instance
     */
    private function optionalString(array $instance, string $key): ?string
    {
        $value = data_get($instance, $key);

        return blank($value) ? null : (string) $value;
    }

    private function toMessageStatus(mixed $status): MessageStatus
    {
        return match (strtolower((string) $status)) {
            'delivered', 'delivery_ack', 'deliveryack' => MessageStatus::Delivered,
            'read', 'played', 'read_ack', 'readack' => MessageStatus::Read,
            'deleted', 'revoked' => MessageStatus::Deleted,
            'failed', 'canceled', 'error' => MessageStatus::Failed,
            'queued', 'pending', 'scheduled' => MessageStatus::Pending,
            default => MessageStatus::Sent,
        };
    }

    private function toMessageType(string $type): MessageType
    {
        return match (strtolower($type)) {
            'image', 'imagemessage' => MessageType::Image,
            'audio', 'ptt', 'audiomessage' => MessageType::Audio,
            'video', 'videomessage' => MessageType::Video,
            'document', 'documentmessage' => MessageType::Document,
            'sticker', 'stickermessage' => MessageType::Sticker,
            'location', 'locationmessage' => MessageType::Location,
            'contact', 'contactmessage' => MessageType::Contact,
            default => MessageType::Text,
        };
    }

    private function phoneFrom(string $identifier): ?string
    {
        if (str_contains($identifier, '@lid')) {
            return null;
        }

        $phone = str($identifier)->before('@')->value();

        return preg_match('/^\d{8,15}$/', $phone) === 1 ? $phone : null;
    }

    private function toCarbon(mixed $timestamp): ?Carbon
    {
        if (blank($timestamp)) {
            return null;
        }

        if (! is_numeric($timestamp)) {
            return rescue(fn (): Carbon => Carbon::parse((string) $timestamp), null, report: false);
        }

        $value = (int) $timestamp;

        return Carbon::createFromTimestampMs($value > 9999999999 ? $value : $value * 1000);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function post(string $path, array $payload = []): Response
    {
        $body = json_encode(
            (object) array_filter($payload, fn (mixed $value): bool => $value !== null),
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE,
        );

        return $this->request()->withBody($body, 'application/json')->post($path);
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl())
            ->withHeaders(['token' => (string) $this->credential('token')])
            ->acceptJson()
            ->timeout(30);
    }

    private function baseUrl(): string
    {
        return rtrim((string) $this->credential('base_url'), '/');
    }
}
