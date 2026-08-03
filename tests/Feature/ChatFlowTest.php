<?php

use App\Domain\Conversations\Enums\ConversationStatus;
use App\Domain\Conversations\Enums\MessageSource;
use App\Models\Card;
use App\Models\ChatFlow;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\ServiceQueue;
use App\Services\Chatbot\FlowEngine;
use Illuminate\Support\Facades\Queue;

function flowWith(array $nodes, array $edges): ChatFlow
{
    return ChatFlow::factory()->create([
        'nodes' => $nodes,
        'edges' => $edges,
        'is_active' => true,
    ]);
}

function node(string $id, string $type, array $data = []): array
{
    return ['id' => $id, 'type' => $type, 'position' => ['x' => 0, 'y' => 0], 'data' => $data];
}

function edge(string $source, string $target, ?string $handle = null): array
{
    return ['id' => "{$source}-{$target}", 'source' => $source, 'target' => $target, 'sourceHandle' => $handle];
}

function conversationRunning(ChatFlow $flow): Conversation
{
    return Conversation::factory()->create([
        'chat_flow_id' => $flow->id,
        'status' => ConversationStatus::Bot,
    ]);
}

function botMessages(Conversation $conversation): array
{
    return $conversation->messages()
        ->where('source', MessageSource::Bot)
        ->orderBy('id')
        ->pluck('body')
        ->all();
}

it('runs the blocks in sequence until it needs an answer', function (): void {
    Queue::fake();
    tenant();

    $flow = flowWith(
        [
            node('start', 'start'),
            node('welcome', 'message', ['text' => 'Bem-vindo!']),
            node('ask', 'question', ['text' => 'Qual o seu nome?', 'save_as' => 'nome']),
            node('bye', 'end', ['text' => 'Obrigado!']),
        ],
        [edge('start', 'welcome'), edge('welcome', 'ask'), edge('ask', 'bye')],
    );

    $conversation = conversationRunning($flow);

    app(FlowEngine::class)->restart($conversation, $flow);

    expect(botMessages($conversation))->toBe(['Bem-vindo!', 'Qual o seu nome?'])
        ->and(app(FlowEngine::class)->isAwaitingInput($conversation->fresh()))->toBeTrue();
});

it('stores the answer and follows to the next block', function (): void {
    Queue::fake();
    tenant();

    $flow = flowWith(
        [
            node('start', 'start'),
            node('ask', 'question', ['text' => 'Qual o seu nome?', 'save_as' => 'nome']),
            node('bye', 'end', ['text' => 'Obrigado!']),
        ],
        [edge('start', 'ask'), edge('ask', 'bye')],
    );

    $conversation = conversationRunning($flow);
    $engine = app(FlowEngine::class);
    $engine->restart($conversation, $flow);

    $answer = Message::factory()->create(['conversation_id' => $conversation->id, 'body' => 'Maria']);

    $engine->advance($conversation->fresh(), $answer);

    $state = $conversation->fresh()->flow_state;

    expect($state['answers']['nome'])->toBe('Maria')
        ->and($state['finished'])->toBeTrue()
        ->and(botMessages($conversation))->toContain('Obrigado!');
});

it('routes the menu through the edge of the chosen option', function (): void {
    Queue::fake();
    tenant();

    $flow = flowWith(
        [
            node('start', 'start'),
            node('menu', 'menu', [
                'text' => 'Escolha:',
                'options' => [
                    ['id' => 'sales', 'label' => 'Comercial'],
                    ['id' => 'support', 'label' => 'Suporte'],
                ],
            ]),
            node('sales-msg', 'end', ['text' => 'Comercial atende você.']),
            node('support-msg', 'end', ['text' => 'Suporte atende você.']),
        ],
        [
            edge('start', 'menu'),
            edge('menu', 'sales-msg', 'sales'),
            edge('menu', 'support-msg', 'support'),
        ],
    );

    $conversation = conversationRunning($flow);
    $engine = app(FlowEngine::class);
    $engine->restart($conversation, $flow);

    $answer = Message::factory()->create(['conversation_id' => $conversation->id, 'body' => '2']);
    $engine->advance($conversation->fresh(), $answer);

    expect(botMessages($conversation))->toContain('Suporte atende você.')
        ->and(botMessages($conversation))->not->toContain('Comercial atende você.');
});

it('asks again when the menu option does not exist', function (): void {
    Queue::fake();
    tenant();

    $flow = flowWith(
        [
            node('start', 'start'),
            node('menu', 'menu', [
                'text' => 'Escolha:',
                'options' => [['id' => 'sales', 'label' => 'Comercial']],
                'invalid_message' => 'Não entendi.',
            ]),
            node('done', 'end', ['text' => 'Pronto.']),
        ],
        [edge('start', 'menu'), edge('menu', 'done', 'sales')],
    );

    $conversation = conversationRunning($flow);
    $engine = app(FlowEngine::class);
    $engine->restart($conversation, $flow);

    $answer = Message::factory()->create(['conversation_id' => $conversation->id, 'body' => 'sei lá']);
    $engine->advance($conversation->fresh(), $answer);

    expect(botMessages($conversation))->toContain('Não entendi.')
        ->and($engine->isAwaitingInput($conversation->fresh()))->toBeTrue();
});

it('takes the true branch of a condition', function (): void {
    Queue::fake();
    tenant();

    $flow = flowWith(
        [
            node('start', 'start'),
            node('check', 'condition', ['field' => 'contato.nome', 'operator' => 'filled']),
            node('known', 'end', ['text' => 'Já te conheço.']),
            node('unknown', 'end', ['text' => 'Prazer!']),
        ],
        [
            edge('start', 'check'),
            edge('check', 'known', 'true'),
            edge('check', 'unknown', 'false'),
        ],
    );

    $conversation = conversationRunning($flow);

    app(FlowEngine::class)->restart($conversation, $flow);

    expect(botMessages($conversation))->toBe(['Já te conheço.']);
});

it('transfers to the queue and stops the flow', function (): void {
    Queue::fake();
    tenant();

    $queue = ServiceQueue::factory()->create();

    $flow = flowWith(
        [
            node('start', 'start'),
            node('to-queue', 'queue', ['service_queue_id' => $queue->id]),
            node('never', 'end', ['text' => 'Não deve chegar aqui.']),
        ],
        [edge('start', 'to-queue'), edge('to-queue', 'never')],
    );

    $conversation = conversationRunning($flow);

    app(FlowEngine::class)->restart($conversation, $flow);

    expect($conversation->fresh()->service_queue_id)->toBe($queue->id)
        ->and($conversation->fresh()->flow_state['finished'])->toBeTrue()
        ->and(botMessages($conversation))->not->toContain('Não deve chegar aqui.');
});

it('sends the card chosen in the message block', function (): void {
    Queue::fake();
    tenant();

    $card = Card::factory()->create(['body' => 'Olá, {{contato.nome}}!']);

    $flow = flowWith(
        [
            node('start', 'start'),
            node('hi', 'message', ['card_id' => $card->id, 'text' => 'Texto ignorado.']),
        ],
        [edge('start', 'hi')],
    );

    $conversation = conversationRunning($flow);

    app(FlowEngine::class)->restart($conversation, $flow);

    expect(botMessages($conversation))->toBe(["Olá, {$conversation->contact->name}!"]);
});

it('fires the transfer card before handing the conversation over', function (): void {
    Queue::fake();
    tenant();

    $queue = ServiceQueue::factory()->create();
    $card = Card::factory()->create(['body' => 'Vou te transferir para o setor.']);

    $flow = flowWith(
        [
            node('start', 'start'),
            node('to-queue', 'queue', ['service_queue_id' => $queue->id, 'card_id' => $card->id]),
        ],
        [edge('start', 'to-queue')],
    );

    $conversation = conversationRunning($flow);

    app(FlowEngine::class)->restart($conversation, $flow);

    expect(botMessages($conversation))->toBe(['Vou te transferir para o setor.'])
        ->and($conversation->fresh()->service_queue_id)->toBe($queue->id);
});

it('ignores an inactive flow', function (): void {
    Queue::fake();
    tenant();

    $flow = flowWith([node('start', 'start'), node('hi', 'message', ['text' => 'Oi'])], [edge('start', 'hi')]);
    $flow->update(['is_active' => false]);

    $conversation = conversationRunning($flow);

    app(FlowEngine::class)->advance($conversation);

    expect(botMessages($conversation))->toBe([]);
});
