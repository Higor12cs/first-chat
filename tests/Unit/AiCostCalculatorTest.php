<?php

use App\Services\Ai\AiCostCalculator;

it('prices a model whose name contains a dot', function (): void {
    config()->set('ai.pricing', ['gpt-5.4-mini' => ['input' => 75, 'output' => 450]]);

    expect((new AiCostCalculator)->microCents('gpt-5.4-mini', 1_000_000, 1_000_000))
        ->toBe(525 * AiCostCalculator::MICRO_CENTS_PER_CENT);
});

it('charges nothing for a model that has no price on the table', function (): void {
    config()->set('ai.pricing', ['gpt-4o-mini' => ['input' => 15, 'output' => 60]]);

    expect((new AiCostCalculator)->microCents('modelo-desconhecido', 1_000_000, 1_000_000))->toBe(0);
});

it('keeps a fraction of a cent instead of rounding a cheap turn up', function (): void {
    config()->set('ai.pricing', ['gpt-4o-mini' => ['input' => 15, 'output' => 60]]);

    expect((new AiCostCalculator)->microCents('gpt-4o-mini', 120, 40))->toBe(4200);
});

it('adds up cheap turns without inflating them', function (): void {
    config()->set('ai.pricing', ['gpt-5.4-mini' => ['input' => 75, 'output' => 450]]);

    $calculator = new AiCostCalculator;

    $turn = $calculator->microCents('gpt-5.4-mini', 2_500, 150);

    expect($turn)->toBe(255_000)
        ->and($turn * 100)->toBeLessThan(30 * AiCostCalculator::MICRO_CENTS_PER_CENT);
});
