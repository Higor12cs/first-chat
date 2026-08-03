<?php

namespace App\Domain\Messaging\DataObjects;

use App\Domain\Messaging\Enums\MessageType;

readonly class ConnectorCapabilities
{
    /**
     * @param  array<int, MessageType>  $messageTypes
     */
    public function __construct(
        public array $messageTypes = [MessageType::Text],
        public bool $incoming = true,
        public bool $outgoing = true,
        public bool $media = false,
        public bool $interactiveButtons = false,
        public bool $templates = false,
        public bool $typingIndicator = false,
        public bool $readReceipts = false,
        public bool $messageDeletion = false,
        public bool $groups = false,
        public bool $session = false,
        public bool $pairingCode = false,
        public ?int $replyWindowHours = null,
    ) {}

    public function supports(MessageType $type): bool
    {
        return in_array($type, $this->messageTypes, true);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'message_types' => array_map(fn (MessageType $type): string => $type->value, $this->messageTypes),
            'incoming' => $this->incoming,
            'outgoing' => $this->outgoing,
            'media' => $this->media,
            'interactive_buttons' => $this->interactiveButtons,
            'templates' => $this->templates,
            'typing_indicator' => $this->typingIndicator,
            'read_receipts' => $this->readReceipts,
            'message_deletion' => $this->messageDeletion,
            'groups' => $this->groups,
            'session' => $this->session,
            'pairing_code' => $this->pairingCode,
            'reply_window_hours' => $this->replyWindowHours,
        ];
    }
}
