<?php

use App\Domain\Conversations\Enums\ConversationStatus;
use App\Models\ChannelConnection;
use App\Models\Contact;
use App\Models\ContactChannel;
use App\Models\Conversation;
use App\Models\Message;

it('brings every message the contact exchanged on the channel', function (): void {
    $tenant = tenant();
    $user = userFor($tenant);

    $connection = ChannelConnection::factory()->create();
    $contact = Contact::factory()->create();
    $channel = ContactChannel::factory()->create([
        'contact_id' => $contact->id,
        'channel_connection_id' => $connection->id,
    ]);

    $anterior = Conversation::factory()->create([
        'contact_id' => $contact->id,
        'contact_channel_id' => $channel->id,
        'channel_connection_id' => $connection->id,
        'status' => ConversationStatus::Closed,
        'closed_at' => now()->subDay(),
    ]);

    $atual = Conversation::factory()->create([
        'contact_id' => $contact->id,
        'contact_channel_id' => $channel->id,
        'channel_connection_id' => $connection->id,
    ]);

    Message::factory()->create(['conversation_id' => $anterior->id, 'body' => 'Falei mês passado']);
    Message::factory()->create(['conversation_id' => $atual->id, 'body' => 'Voltei hoje']);

    $response = $this->actingAs($user)->get("/atendimentos/{$atual->id}");

    $bodies = collect($response->viewData('page')['props']['messages']['data'])->pluck('body');

    expect($bodies)->toContain('Falei mês passado')
        ->and($bodies)->toContain('Voltei hoje');
});

it('marks where each conversation started and ended', function (): void {
    $tenant = tenant();
    $user = userFor($tenant);

    $connection = ChannelConnection::factory()->create();
    $contact = Contact::factory()->create();
    $channel = ContactChannel::factory()->create([
        'contact_id' => $contact->id,
        'channel_connection_id' => $connection->id,
    ]);

    $fechado = Conversation::factory()->create([
        'contact_id' => $contact->id,
        'contact_channel_id' => $channel->id,
        'channel_connection_id' => $connection->id,
        'status' => ConversationStatus::Closed,
        'closed_at' => now()->subDay(),
    ]);

    $aberto = Conversation::factory()->create([
        'contact_id' => $contact->id,
        'contact_channel_id' => $channel->id,
        'channel_connection_id' => $connection->id,
    ]);

    $timeline = collect(
        $this->actingAs($user)->get("/atendimentos/{$aberto->id}")->viewData('page')['props']['timeline']
    );

    expect($timeline)->toHaveCount(3)
        ->and($timeline->where('kind', 'started'))->toHaveCount(2)
        ->and($timeline->where('kind', 'closed'))->toHaveCount(1)
        ->and($timeline->pluck('at')->all())->toBe($timeline->pluck('at')->sort()->values()->all());
});

it('keeps the history of one contact out of another', function (): void {
    $tenant = tenant();
    $user = userFor($tenant);

    $connection = ChannelConnection::factory()->create();

    $meu = ContactChannel::factory()->create(['channel_connection_id' => $connection->id]);
    $outro = ContactChannel::factory()->create(['channel_connection_id' => $connection->id]);

    $conversa = Conversation::factory()->create([
        'contact_id' => $meu->contact_id,
        'contact_channel_id' => $meu->id,
        'channel_connection_id' => $connection->id,
    ]);

    $alheia = Conversation::factory()->create([
        'contact_id' => $outro->contact_id,
        'contact_channel_id' => $outro->id,
        'channel_connection_id' => $connection->id,
    ]);

    Message::factory()->create(['conversation_id' => $conversa->id, 'body' => 'Minha mensagem']);
    Message::factory()->create(['conversation_id' => $alheia->id, 'body' => 'Mensagem de outro contato']);

    $bodies = collect(
        $this->actingAs($user)->get("/atendimentos/{$conversa->id}")->viewData('page')['props']['messages']['data']
    )->pluck('body');

    expect($bodies)->toContain('Minha mensagem')
        ->and($bodies)->not->toContain('Mensagem de outro contato');
});

it('opens the history on the most recent page', function (): void {
    $tenant = tenant();
    $user = userFor($tenant);

    $conversa = Conversation::factory()->create();

    foreach (range(1, 95) as $numero) {
        Message::factory()->create(['conversation_id' => $conversa->id, 'body' => "Mensagem {$numero}"]);
    }

    $messages = $this->actingAs($user)
        ->get("/atendimentos/{$conversa->id}")
        ->viewData('page')['props']['messages'];

    $bodies = collect($messages['data'])->pluck('body');

    expect($messages['meta']['current_page'])->toBe(3)
        ->and($bodies)->toHaveCount(15)
        ->and($bodies->first())->toBe('Mensagem 81')
        ->and($bodies->last())->toBe('Mensagem 95');
});

it('walks back to the older pages in chronological order', function (): void {
    $tenant = tenant();
    $user = userFor($tenant);

    $conversa = Conversation::factory()->create();

    foreach (range(1, 95) as $numero) {
        Message::factory()->create(['conversation_id' => $conversa->id, 'body' => "Mensagem {$numero}"]);
    }

    $bodies = collect(
        $this->actingAs($user)
            ->get("/atendimentos/{$conversa->id}?page=1")
            ->viewData('page')['props']['messages']['data']
    )->pluck('body');

    expect($bodies)->toHaveCount(40)
        ->and($bodies->first())->toBe('Mensagem 1')
        ->and($bodies->last())->toBe('Mensagem 40');
});
