<?php

namespace App\Domain\Messaging\Contracts;

use Illuminate\Support\Carbon;

interface ConnectorEvent
{
    public function occurredAt(): Carbon;
}
