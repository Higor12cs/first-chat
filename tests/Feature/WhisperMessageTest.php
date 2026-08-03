<?php

use App\Domain\Messaging\Enums\MessageStatus;
use App\Jobs\Messaging\DeliverMessage;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();

    $this->tenant = tenant();
    $this->user = userFor($this->tenant);
    $this->conversation = Conversation::factory()->create(['assigned_user_id' => $this->user->id]);
});

it('keeps the whisper inside the platform', function (): void {
    $this->actingAs($this->user)
        ->postJson("/atendimentos/{$this->conversation->id}/mensagens", [
            'body' => 'O cliente já reclamou disso antes.',
            'internal' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('message.is_internal', true);

    $message = Message::query()->latest('id')->first();

    expect($message->is_internal)->toBeTrue()
        ->and($message->status)->toBe(MessageStatus::Sent);

    Queue::assertNotPushed(DeliverMessage::class);
});

it('delivers an ordinary message as usual', function (): void {
    $this->actingAs($this->user)
        ->postJson("/atendimentos/{$this->conversation->id}/mensagens", ['body' => 'Bom dia!'])
        ->assertCreated()
        ->assertJsonPath('message.is_internal', false);

    Queue::assertPushed(DeliverMessage::class);
});

it('never signs a whisper', function (): void {
    membershipFor($this->user, $this->tenant, ['signs_messages' => true]);

    $this->actingAs($this->user)
        ->postJson("/atendimentos/{$this->conversation->id}/mensagens", [
            'body' => 'Só entre nós.',
            'internal' => true,
        ])
        ->assertCreated();

    expect(Message::query()->latest('id')->first()->body)->toBe('Só entre nós.');
});

it('does not count a whisper as the first response to the contact', function (): void {
    $this->actingAs($this->user)
        ->postJson("/atendimentos/{$this->conversation->id}/mensagens", [
            'body' => 'Alguém sabe o preço?',
            'internal' => true,
        ])
        ->assertCreated();

    expect($this->conversation->fresh()->first_response_at)->toBeNull();

    $this->actingAs($this->user)
        ->postJson("/atendimentos/{$this->conversation->id}/mensagens", ['body' => 'Bom dia!'])
        ->assertCreated();

    expect($this->conversation->fresh()->first_response_at)->not->toBeNull();
});

it('refuses to resend a whisper', function (): void {
    $this->actingAs($this->user)
        ->postJson("/atendimentos/{$this->conversation->id}/mensagens", [
            'body' => 'Só entre nós.',
            'internal' => true,
        ])
        ->assertCreated();

    $message = Message::query()->latest('id')->first();

    $this->actingAs($this->user)
        ->postJson("/atendimentos/{$this->conversation->id}/mensagens/{$message->id}/reenviar")
        ->assertStatus(422);
});

it('signs only the conversation the agent asked to sign', function (): void {
    $this->actingAs($this->user)
        ->postJson("/atendimentos/{$this->conversation->id}/mensagens", [
            'body' => 'Bom dia!',
            'sign' => true,
        ])
        ->assertCreated();

    expect(Message::query()->latest('id')->first()->body)
        ->toBe("#*_{$this->user->name}:_*\nBom dia!");
});

it('lets the conversation turn the signature off against the preference', function (): void {
    membershipFor($this->user, $this->tenant, ['signs_messages' => true]);

    $this->actingAs($this->user)
        ->postJson("/atendimentos/{$this->conversation->id}/mensagens", [
            'body' => 'Bom dia!',
            'sign' => false,
        ])
        ->assertCreated();

    expect(Message::query()->latest('id')->first()->body)->toBe('Bom dia!');
});

it('falls back to the preference when the conversation says nothing', function (): void {
    membershipFor($this->user, $this->tenant, ['signs_messages' => true]);

    $this->actingAs($this->user)
        ->postJson("/atendimentos/{$this->conversation->id}/mensagens", ['body' => 'Bom dia!'])
        ->assertCreated();

    expect(Message::query()->latest('id')->first()->body)
        ->toBe("#*_{$this->user->name}:_*\nBom dia!");
});
