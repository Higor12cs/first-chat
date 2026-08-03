<?php

use App\Services\Ai\AiCostCalculator;

it('prices a model whose name contains a dot', function (): void {
    config()->set('ai.pricing', ['gpt-5.4-mini' => ['input' => 75, 'output' => 450]]);

    expect((new AiCostCalculator)->cents('gpt-5.4-mini', 1_000_000, 1_000_000))->toBe(525);
});

it('charges nothing for a model that has no price on the table', function (): void {
    config()->set('ai.pricing', ['gpt-4o-mini' => ['input' => 15, 'output' => 60]]);

    expect((new AiCostCalculator)->cents('modelo-desconhecido', 1_000_000, 1_000_000))->toBe(0);
});

it('rounds a fraction of a cent up so cheap turns still count', function (): void {
    config()->set('ai.pricing', ['gpt-4o-mini' => ['input' => 15, 'output' => 60]]);

    expect((new AiCostCalculator)->cents('gpt-4o-mini', 120, 40))->toBe(1);
});
