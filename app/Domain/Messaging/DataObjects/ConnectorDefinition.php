<?php

namespace App\Domain\Messaging\DataObjects;

use App\Domain\Messaging\Enums\Channel;
use App\Domain\Messaging\Enums\MessageType;

readonly class ConnectorDefinition
{
    /**
     * @param  array<string, mixed>  $credentials
     */
    public function __construct(
        public string $key,
        public string $label,
        public Channel $channel,
        public string $class,
        public ConnectorCapabilities $capabilities,
        public array $credentials = [],
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfig(string $key, array $config): self
    {
        $capabilities = $config['capabilities'] ?? [];

        return new self(
            key: $key,
            label: $config['label'],
            channel: $config['channel'],
            class: $config['class'],
            capabilities: new ConnectorCapabilities(
                messageTypes: $capabilities['message_types'] ?? [MessageType::Text],
                incoming: $capabilities['incoming'] ?? true,
                outgoing: $capabilities['outgoing'] ?? true,
                media: $capabilities['media'] ?? false,
                interactiveButtons: $capabilities['interactive_buttons'] ?? false,
                templates: $capabilities['templates'] ?? false,
                typingIndicator: $capabilities['typing_indicator'] ?? false,
                readReceipts: $capabilities['read_receipts'] ?? false,
                messageDeletion: $capabilities['message_deletion'] ?? false,
                groups: $capabilities['groups'] ?? false,
                session: $capabilities['session'] ?? false,
                pairingCode: $capabilities['pairing_code'] ?? false,
                replyWindowHours: $capabilities['reply_window_hours'] ?? null,
            ),
            credentials: array_filter($config['credentials'] ?? [], fn (mixed $value): bool => $value !== null && $value !== ''),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'channel' => $this->channel->value,
            'channel_label' => $this->channel->label(),
            'capabilities' => $this->capabilities->toArray(),
        ];
    }
}
