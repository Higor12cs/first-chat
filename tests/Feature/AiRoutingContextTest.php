<?php

use App\Actions\Ai\HandleAiTurn;
use App\Domain\Ai\Tools\ApplyTagTool;
use App\Domain\Ai\Tools\TransferToQueueTool;
use App\Domain\Conversations\Enums\ConversationStatus;
use App\Models\AiObjective;
use App\Models\Conversation;
use App\Models\ServiceQueue;
use App\Models\Tag;
use Illuminate\Support\Facades\Http;

function aiTurnPrompt(array $tools, ?ServiceQueue $handoff = null): string
{
    Http::fake(['*' => Http::response(['choices' => [['message' => ['content' => null]]]])]);

    $objective = AiObjective::factory()->create([
        'tools' => $tools,
        'handoff_service_queue_id' => $handoff?->id,
    ]);

    $conversation = Conversation::factory()->create([
        'status' => ConversationStatus::Ai,
        'ai_objective_id' => $objective->id,
    ]);

    app(HandleAiTurn::class)->handle($conversation);

    $prompt = '';

    Http::assertSent(function ($request) use (&$prompt): bool {
        $prompt = collect($request['messages'])->firstWhere('role', 'system')['content'];

        return true;
    });

    return $prompt;
}

beforeEach(function (): void {
    tenant();
});

it('lists the queues in the prompt so the model can pick the right one', function (): void {
    ServiceQueue::factory()->create([
        'slug' => 'financeiro',
        'name' => 'Financeiro',
        'description' => 'Boletos, notas fiscais e cobrança.',
        'is_active' => true,
    ]);

    $prompt = aiTurnPrompt(['transfer_to_queue']);

    expect($prompt)->toContain('- financeiro: Financeiro — Boletos, notas fiscais e cobrança.');
});

it('keeps an inactive queue out of the prompt', function (): void {
    ServiceQueue::factory()->create(['slug' => 'suporte', 'is_active' => true]);
    ServiceQueue::factory()->create(['slug' => 'desativada', 'is_active' => false]);

    $prompt = aiTurnPrompt(['transfer_to_queue']);

    expect($prompt)->toContain('suporte')
        ->and($prompt)->not->toContain('desativada');
});

it('tells the model there is nowhere to transfer when no queue exists', function (): void {
    $prompt = aiTurnPrompt(['transfer_to_queue']);

    expect($prompt)->toContain('Não há setores disponíveis para transferência');
});

it('lists the tags in the prompt so the model stops inventing slugs', function (): void {
    Tag::factory()->create(['slug' => 'lead-quente', 'name' => 'Lead Quente', 'description' => null]);

    $prompt = aiTurnPrompt(['apply_tag']);

    expect($prompt)->toContain('- lead-quente: Lead Quente');
});

it('names the queue that request_human sends the conversation to', function (): void {
    $queue = ServiceQueue::factory()->create(['slug' => 'vendas', 'name' => 'Vendas', 'is_active' => true]);

    $prompt = aiTurnPrompt(['request_human'], $queue);

    expect($prompt)->toContain('request_human coloca o atendimento no setor Vendas.');
});

it('leaves the catalogs out when the objective cannot route', function (): void {
    ServiceQueue::factory()->create(['slug' => 'financeiro', 'is_active' => true]);
    Tag::factory()->create(['slug' => 'lead-quente']);

    $prompt = aiTurnPrompt(['qualify_lead']);

    expect($prompt)->not->toContain('financeiro')
        ->and($prompt)->not->toContain('lead-quente');
});

it('offers the model only tags that exist', function (): void {
    Tag::factory()->create(['slug' => 'lead-quente', 'name' => 'Lead Quente']);
    Tag::factory()->create(['slug' => 'sem-interesse', 'name' => 'Sem Interesse']);

    $schema = app(ApplyTagTool::class)->schema();

    expect($schema['properties']['tag_slug']['enum'])->toEqualCanonicalizing(['lead-quente', 'sem-interesse'])
        ->and($schema['properties']['tag_slug']['description'])->toContain('lead-quente (Lead Quente)');
});

it('answers an unknown tag with the valid options', function (): void {
    Tag::factory()->create(['slug' => 'lead-quente']);

    $conversation = Conversation::factory()->create();

    $result = app(ApplyTagTool::class)->execute($conversation, ['tag_slug' => 'inventada']);

    expect($result)->toContain('lead-quente');
});

it('answers an unknown queue with the valid options', function (): void {
    ServiceQueue::factory()->create(['slug' => 'financeiro', 'is_active' => true]);

    $conversation = Conversation::factory()->create();

    $result = app(TransferToQueueTool::class)->execute($conversation, ['queue_slug' => 'inventada']);

    expect($result)->toContain('financeiro')
        ->and($conversation->fresh()->service_queue_id)->toBeNull();
});

it('refuses to transfer the conversation back to the objective already running it', function (): void {
    $objective = AiObjective::factory()->create();

    $queue = ServiceQueue::factory()->create([
        'slug' => 'comercial',
        'name' => 'Comercial',
        'is_active' => true,
        'ai_objective_id' => $objective->id,
    ]);

    $conversation = Conversation::factory()->create([
        'status' => ConversationStatus::Ai,
        'ai_objective_id' => $objective->id,
    ]);

    $result = app(TransferToQueueTool::class)->execute($conversation, ['queue_slug' => $queue->slug]);

    expect($result)->toContain('request_human')
        ->and($conversation->fresh()->ai_objective_id)->toBe($objective->id);
});

it('will not transfer to an inactive queue', function (): void {
    ServiceQueue::factory()->create(['slug' => 'desativada', 'is_active' => false]);

    $conversation = Conversation::factory()->create();

    $result = app(TransferToQueueTool::class)->execute($conversation, ['queue_slug' => 'desativada']);

    expect($result)->toContain('Nenhum setor')
        ->and($conversation->fresh()->service_queue_id)->toBeNull();
});
