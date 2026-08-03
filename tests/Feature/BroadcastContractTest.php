<?php

use App\Events\Conversations\ConversationUpdated;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

/**
 * @return array<int, class-string>
 */
function queueableClasses(): array
{
    $classes = [];

    foreach (Finder::create()->files()->in([app_path('Events'), app_path('Jobs')])->name('*.php') as $file) {
        $class = 'App'.Str::of($file->getRealPath())
            ->after(app_path())
            ->replace(['/', '\\'], '\\')
            ->replace('.php', '')
            ->value();

        if (class_exists($class)) {
            $classes[] = $class;
        }
    }

    return $classes;
}

it('keeps the inbox broadcast within the default reverb budget', function (): void {
    tenant();

    $conversation = Conversation::factory()->create();

    Message::factory()->create([
        'conversation_id' => $conversation->id,
        'body' => str_repeat('ぁ', 4096),
    ]);

    $payload = (new ConversationUpdated($conversation->fresh()))->broadcastWith();

    expect(strlen((string) json_encode($payload)))->toBeLessThan(4_000);
});

it('does not put the agent private details on the tenant channel', function (): void {
    $tenant = tenant();
    $agent = userFor($tenant);

    $conversation = Conversation::factory()->create(['assigned_user_id' => $agent->id]);

    $payload = (string) json_encode((new ConversationUpdated($conversation))->broadcastWith(), JSON_UNESCAPED_UNICODE);

    expect($payload)->toContain($agent->name)
        ->and($payload)->not->toContain($agent->email);
});

it('never names a constructor property the queue reserves', function (): void {
    $reserved = ['connection', 'queue', 'delay', 'afterCommit', 'chainConnection', 'chainQueue'];

    $classes = queueableClasses();

    expect($classes)->not->toBeEmpty();

    foreach ($classes as $class) {
        $reflection = new ReflectionClass($class);

        if (! $reflection->implementsInterface(ShouldBroadcast::class) && ! $reflection->implementsInterface(ShouldQueue::class)) {
            continue;
        }

        foreach ($reflection->getConstructor()?->getParameters() ?? [] as $parameter) {
            expect($parameter->getName())->not->toBeIn(
                $reserved,
                "{$class} declara \${$parameter->getName()}, nome que a fila reserva.",
            );
        }
    }
});
