<?php

namespace App\Domain\Messaging\Contracts;

interface SupportsPresence
{
    public function sendTyping(string $recipient, bool $typing = true): void;

    public function markAsRead(string $recipient, string ...$externalIds): void;
}
