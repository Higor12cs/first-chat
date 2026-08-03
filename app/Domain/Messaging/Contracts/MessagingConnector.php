<?php

namespace App\Domain\Messaging\Contracts;

use App\Domain\Messaging\DataObjects\ConnectorCapabilities;
use App\Domain\Messaging\DataObjects\MessageResult;
use App\Domain\Messaging\DataObjects\OutgoingMessage;

interface MessagingConnector
{
    public function send(OutgoingMessage $message): MessageResult;

    public function capabilities(): ConnectorCapabilities;
}
