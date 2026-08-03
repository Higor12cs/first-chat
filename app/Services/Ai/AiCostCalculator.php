<?php

namespace App\Services\Ai;

class AiCostCalculator
{
    public const MICRO_CENTS_PER_CENT = 1_000_000;

    public function microCents(string $model, int $inputTokens, int $outputTokens): int
    {
        $pricing = config('ai.pricing', [])[$model] ?? null;

        if (! is_array($pricing)) {
            return 0;
        }

        return $inputTokens * (int) ($pricing['input'] ?? 0)
            + $outputTokens * (int) ($pricing['output'] ?? 0);
    }
}
