<?php

namespace App\Domain\Messaging\Contracts;

interface DeletesMessages
{
    public function deleteMessage(string $externalId): void;
}
