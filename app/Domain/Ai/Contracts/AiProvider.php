<?php

namespace App\Domain\Ai\Contracts;

use App\Domain\Ai\DataObjects\AiRequest;
use App\Domain\Ai\DataObjects\AiResponse;

interface AiProvider
{
    public function chat(AiRequest $request): AiResponse;
}
