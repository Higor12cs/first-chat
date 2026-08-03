<?php

use App\Actions\Ai\HandleAiTurn;
use App\Domain\Ai\DataObjects\AiResponse;
use App\Domain\Conversations\Enums\ConversationStatus;
use App\Models\AiInteraction;
use App\Models\AiObjective;
use App\Models\Conversation;
use App\Services\Ai\AiCostCalculator;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
    tenant();
});

it('records what a cheap turn really cost instead of a whole cent', function (): void {
    fakeProvider([new AiResponse(content: 'Claro!', inputTokens: 2_500, outputTokens: 150)]);

    $objective = AiObjective::factory()->create(['model' => 'gpt-5.4-mini', 'tools' => []]);

    app(HandleAiTurn::class)->handle(Conversation::factory()->create([
        'status' => ConversationStatus::Ai,
        'ai_objective_id' => $objective->id,
    ]));

    expect(AiInteraction::query()->first()->cost_micro_cents)->toBe(255_000);
});

it('spends the whole budget instead of stopping four times early', function (): void {
    $objective = AiObjective::factory()->create(['cost_limit_cents' => 10]);

    AiInteraction::factory()->count(9)->create([
        'ai_objective_id' => $objective->id,
        'cost_micro_cents' => 250_000,
    ]);

    expect($objective->spentMicroCents())->toBe(2_250_000)
        ->and($objective->hasBudgetLeft())->toBeTrue();
});

it('stops once the budget is genuinely gone', function (): void {
    $objective = AiObjective::factory()->create(['cost_limit_cents' => 10]);

    AiInteraction::factory()->create([
        'ai_objective_id' => $objective->id,
        'cost_micro_cents' => 10 * AiCostCalculator::MICRO_CENTS_PER_CENT,
    ]);

    expect($objective->hasBudgetLeft())->toBeFalse();
});

it('keeps a month of cheap turns from drifting away from the real bill', function (): void {
    $objective = AiObjective::factory()->create();

    AiInteraction::factory()->count(1_000)->create([
        'ai_objective_id' => $objective->id,
        'cost_micro_cents' => 255_000,
    ]);

    expect($objective->spentMicroCents())->toBe(255 * AiCostCalculator::MICRO_CENTS_PER_CENT);
});
