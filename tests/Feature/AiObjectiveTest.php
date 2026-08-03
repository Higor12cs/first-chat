<?php

use App\Actions\Ai\HandleAiTurn;
use App\Domain\Ai\Contracts\AiProvider;
use App\Domain\Ai\Contracts\AudioTranscriber;
use App\Domain\Ai\DataObjects\AiRequest;
use App\Domain\Ai\DataObjects\AiResponse;
use App\Domain\Ai\DataObjects\AiToolCall;
use App\Domain\Conversations\Enums\ConversationStatus;
use App\Domain\Conversations\Enums\MessageSource;
use App\Domain\Messaging\Enums\MessageType;
use App\Events\Ai\AiHandoffRequested;
use App\Models\AiInteraction;
use App\Models\AiObjective;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\ServiceQueue;
use App\Services\Ai\AiManager;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

function fakeProvider(array $responses): AiProvider
{
    $provider = new class($responses) implements AiProvider
    {
        public array $requests = [];

        public function __construct(private array $responses) {}

        public function chat(AiRequest $request): AiResponse
        {
            $this->requests[] = $request;

            return array_shift($this->responses) ?? new AiResponse(content: 'fim');
        }
    };

    $manager = Mockery::mock(AiManager::class);
    $manager->shouldReceive('provider')->andReturn($provider);
    app()->instance(AiManager::class, $manager);

    return $provider;
}

function conversationWithObjective(array $objectiveAttributes = []): Conversation
{
    $objective = AiObjective::factory()->create([
        'provider' => 'openai',
        'model' => 'gpt-4o-mini',
        'is_active' => true,
        ...$objectiveAttributes,
    ]);

    $conversation = Conversation::factory()->create([
        'status' => ConversationStatus::Ai,
        'ai_objective_id' => $objective->id,
    ]);

    Message::factory()->create(['conversation_id' => $conversation->id, 'body' => 'Quero um orçamento.']);

    return $conversation->fresh();
}

it('answers the contact and records the interaction cost', function (): void {
    Queue::fake();
    tenant();

    fakeProvider([new AiResponse(content: 'Claro, posso ajudar!', inputTokens: 120, outputTokens: 40)]);

    $conversation = conversationWithObjective();

    app(HandleAiTurn::class)->handle($conversation);

    $reply = Message::query()->where('source', MessageSource::Ai)->first();

    expect($reply)->not->toBeNull()
        ->and($reply->body)->toBe('Claro, posso ajudar!')
        ->and(AiInteraction::query()->count())->toBe(1)
        ->and(AiInteraction::query()->first()->cost_cents)->toBeGreaterThan(0);
});

it('sends the objective prompt and parameters to the provider', function (): void {
    Queue::fake();
    tenant();

    $provider = fakeProvider([new AiResponse(content: 'ok')]);

    $conversation = conversationWithObjective([
        'system_prompt' => 'Você qualifica leads.',
        'temperature' => 0.2,
        'max_tokens' => 512,
    ]);

    app(HandleAiTurn::class)->handle($conversation);

    $request = $provider->requests[0];

    expect($request->model)->toBe('gpt-4o-mini')
        ->and($request->temperature)->toBe(0.2)
        ->and($request->maxTokens)->toBe(512)
        ->and($request->systemPrompt)->toContain('Você qualifica leads.');
});

it('executes the tools the objective granted', function (): void {
    Queue::fake();
    tenant();

    $queue = ServiceQueue::factory()->create(['name' => 'Comercial', 'slug' => 'comercial']);

    fakeProvider([
        new AiResponse(toolCalls: [new AiToolCall('call-1', 'transfer_to_queue', ['queue_slug' => 'comercial'])]),
        new AiResponse(content: 'Transferi você para o comercial.'),
    ]);

    $conversation = conversationWithObjective(['tools' => ['transfer_to_queue']]);

    app(HandleAiTurn::class)->handle($conversation);

    expect($conversation->fresh()->service_queue_id)->toBe($queue->id);
});

it('tells the contact what it is about to do before doing it', function (): void {
    Queue::fake();
    tenant();

    ServiceQueue::factory()->create(['name' => 'Comercial', 'slug' => 'comercial']);

    fakeProvider([
        new AiResponse(
            content: 'Vou te passar para o comercial, um momento.',
            toolCalls: [new AiToolCall('call-1', 'transfer_to_queue', ['queue_slug' => 'comercial'])],
        ),
    ]);

    $conversation = conversationWithObjective(['tools' => ['transfer_to_queue']]);

    app(HandleAiTurn::class)->handle($conversation);

    $announcement = Message::query()->where('source', MessageSource::Ai)->first();

    expect($announcement)->not->toBeNull()
        ->and($announcement->body)->toBe('Vou te passar para o comercial, um momento.')
        ->and($conversation->fresh()->serviceQueue->slug)->toBe('comercial');
});

it('reads the audio of the contact through a transcription', function (): void {
    Queue::fake();
    tenant();

    app()->instance(AudioTranscriber::class, new class implements AudioTranscriber
    {
        public function transcribe(string $url, ?string $mimeType = null): ?string
        {
            return 'Preciso de ajuda com a segunda via do boleto.';
        }
    });

    $provider = fakeProvider([new AiResponse(content: 'Claro!')]);
    $conversation = conversationWithObjective();

    $audio = Message::factory()->create([
        'conversation_id' => $conversation->id,
        'type' => MessageType::Audio,
        'body' => null,
        'media_url' => 'https://exemplo.test/audio.ogg',
        'media_mime_type' => 'audio/ogg',
    ]);

    app(HandleAiTurn::class)->handle($conversation);

    $sent = collect($provider->requests[0]->messages)->pluck('content')->implode("\n");

    expect($sent)->toContain('segunda via do boleto')
        ->and($audio->fresh()->transcription)->toBe('Preciso de ajuda com a segunda via do boleto.');
});

it('transcribes each audio only once', function (): void {
    Queue::fake();
    tenant();

    $transcriber = new class implements AudioTranscriber
    {
        public int $calls = 0;

        public function transcribe(string $url, ?string $mimeType = null): ?string
        {
            $this->calls++;

            return 'Bom dia.';
        }
    };

    app()->instance(AudioTranscriber::class, $transcriber);
    fakeProvider([new AiResponse(content: 'ok'), new AiResponse(content: 'ok')]);

    $conversation = conversationWithObjective();

    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'type' => MessageType::Audio,
        'body' => null,
        'media_url' => 'https://exemplo.test/audio.ogg',
    ]);

    app(HandleAiTurn::class)->handle($conversation);
    app(HandleAiTurn::class)->handle($conversation->fresh());

    expect($transcriber->calls)->toBe(1);
});

it('asks the model to announce every action', function (): void {
    Queue::fake();
    tenant();

    $provider = fakeProvider([new AiResponse(content: 'ok')]);

    app(HandleAiTurn::class)->handle(conversationWithObjective());

    expect($provider->requests[0]->systemPrompt)->toContain('Antes de usar qualquer ferramenta');
});

it('carries the tool calls into the next round', function (): void {
    Queue::fake();
    tenant();

    $call = new AiToolCall('call-1', 'add_note', ['note' => 'Cliente pediu orçamento.']);

    $provider = fakeProvider([
        new AiResponse(toolCalls: [$call]),
        new AiResponse(content: 'Anotado!'),
    ]);

    app(HandleAiTurn::class)->handle(conversationWithObjective(['tools' => ['add_note']]));

    $history = $provider->requests[1]->messages;
    $assistant = collect($history)->firstWhere('role', 'assistant');

    expect($assistant->extra['tool_calls'])->toBe([$call])
        ->and(collect($history)->firstWhere('role', 'tool')->extra['tool_call_id'])->toBe('call-1');
});

it('stops answering when the objective runs out of budget', function (): void {
    Queue::fake();
    Event::fake([AiHandoffRequested::class]);
    tenant();

    fakeProvider([new AiResponse(content: 'nunca chega aqui')]);

    $conversation = conversationWithObjective(['cost_limit_cents' => 10]);

    AiInteraction::factory()->create([
        'conversation_id' => $conversation->id,
        'ai_objective_id' => $conversation->ai_objective_id,
        'cost_cents' => 50,
    ]);

    app(HandleAiTurn::class)->handle($conversation);

    expect(Message::query()->where('source', MessageSource::Ai)->count())->toBe(0);

    Event::assertDispatched(AiHandoffRequested::class);
});

it('stops answering after the configured number of turns', function (): void {
    Queue::fake();
    Event::fake([AiHandoffRequested::class]);
    tenant();

    fakeProvider([new AiResponse(content: 'nunca chega aqui')]);

    $conversation = conversationWithObjective(['max_turns' => 2]);

    AiInteraction::factory()->count(2)->create([
        'conversation_id' => $conversation->id,
        'ai_objective_id' => $conversation->ai_objective_id,
        'cost_cents' => 1,
    ]);

    app(HandleAiTurn::class)->handle($conversation);

    expect(Message::query()->where('source', MessageSource::Ai)->count())->toBe(0);

    Event::assertDispatched(AiHandoffRequested::class);
});

it('does nothing when the objective is inactive', function (): void {
    Queue::fake();
    tenant();

    fakeProvider([new AiResponse(content: 'nunca chega aqui')]);

    $conversation = conversationWithObjective(['is_active' => false]);

    app(HandleAiTurn::class)->handle($conversation);

    expect(Message::query()->where('source', MessageSource::Ai)->count())->toBe(0)
        ->and(AiInteraction::query()->count())->toBe(0);
});
