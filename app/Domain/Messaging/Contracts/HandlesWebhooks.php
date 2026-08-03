<?php

namespace App\Domain\Messaging\Contracts;

use Illuminate\Http\Request;

interface HandlesWebhooks
{
    public function verifyWebhook(Request $request): bool;

    /**
     * @return array<int, ConnectorEvent>
     */
    public function parseWebhook(Request $request): array;
}
