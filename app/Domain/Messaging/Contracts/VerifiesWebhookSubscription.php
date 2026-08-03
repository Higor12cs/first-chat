<?php

namespace App\Domain\Messaging\Contracts;

use Illuminate\Http\Request;

interface VerifiesWebhookSubscription
{
    public function respondToSubscriptionChallenge(Request $request): ?string;
}
