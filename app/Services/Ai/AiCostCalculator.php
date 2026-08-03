<?php

namespace App\Services\Ai;

class AiCostCalculator
{
    public function cents(string $model, int $inputTokens, int $outputTokens): int
    {
        $pricing = config('ai.pricing', [])[$model] ?? null;

        if (! is_array($pricing)) {
            return 0;
        }

        $cost = ($inputTokens / 1_000_000) * (int) ($pricing['input'] ?? 0)
            + ($outputTokens / 1_000_000) * (int) ($pricing['output'] ?? 0);

        return (int) ceil($cost);
    }
}
