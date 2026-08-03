<?php

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\ServiceQueue;

beforeEach(function (): void {
    $this->tenant = tenant();
});

it('finds active conversations, contacts and modules', function (): void {
    $user = userFor($this->tenant);
    $contact = Contact::factory()->create(['name' => 'Joana Ribeiro', 'phone' => '5511988887777']);
    $conversation = Conversation::factory()->create([
        'contact_id' => $contact->id,
        'assigned_user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->getJson('/busca?busca=Joana')
        ->assertOk()
        ->assertJsonPath('conversations.0.id', $conversation->id)
        ->assertJsonPath('conversations.0.label', 'Joana Ribeiro')
        ->assertJsonPath('contacts.0.id', $contact->id);
});

it('matches a module ignoring the accents', function (): void {
    $user = userFor($this->tenant);

    $this->actingAs($user)
        ->getJson('/busca?busca=papeis')
        ->assertOk()
        ->assertJsonPath('modules.0.href', '/papeis');
});

it('hides the conversations of other agents', function (): void {
    $other = userFor($this->tenant);
    $restricted = userFor($this->tenant, ['conversations.view', 'contacts.view']);
    $contact = Contact::factory()->create(['name' => 'Joana Ribeiro']);

    Conversation::factory()->create([
        'contact_id' => $contact->id,
        'assigned_user_id' => $other->id,
    ]);

    $this->actingAs($restricted)
        ->getJson('/busca?busca=Joana')
        ->assertOk()
        ->assertJsonCount(0, 'conversations');
});

it('offers only the modules the user may open', function (): void {
    $restricted = userFor($this->tenant, ['conversations.view']);

    $modules = $this->actingAs($restricted)->getJson('/busca')->assertOk()->json('modules');

    expect(array_column($modules, 'href'))
        ->toContain('/atendimentos')
        ->not->toContain('/papeis');
});

it('leaves closed conversations out', function (): void {
    $user = userFor($this->tenant);
    ServiceQueue::factory()->create(['is_default' => true]);
    $contact = Contact::factory()->create(['name' => 'Joana Ribeiro']);

    Conversation::factory()->closed()->create([
        'contact_id' => $contact->id,
        'assigned_user_id' => $user->id,
    ]);

    $this->actingAs($user)
        ->getJson('/busca?busca=Joana')
        ->assertOk()
        ->assertJsonCount(0, 'conversations');
});
