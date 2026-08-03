<?php

namespace App\Domain\Ai\Exceptions;

use RuntimeException;

class AiException extends RuntimeException
{
    public static function unknownProvider(string $provider): self
    {
        return new self("AI provider [{$provider}] is not registered in config/ai.php.");
    }

    public static function requestFailed(string $provider, string $reason): self
    {
        return new self("Provider [{$provider}] request failed: {$reason}");
    }

    public static function missingApiKey(string $provider): self
    {
        return new self("Provider [{$provider}] has no API key configured.");
    }
}
