<?php

use App\Domain\Conversations\Enums\ConversationSection;
use App\Domain\Conversations\Enums\ConversationStatus;
use App\Models\Contact;
use App\Models\Conversation;

beforeEach(function (): void {
    $this->tenant = tenant();
    $this->user = userFor($this->tenant);
});

it('puts every open conversation in exactly one section', function (): void {
    $bot = Conversation::factory()->create(['status' => ConversationStatus::Bot]);
    $waiting = Conversation::factory()->create(['status' => ConversationStatus::Pending]);
    $manual = Conversation::factory()->create(['status' => ConversationStatus::Open, 'assigned_user_id' => $this->user->id]);
    $group = Conversation::factory()->create(['status' => ConversationStatus::Open, 'is_group' => true, 'assigned_user_id' => $this->user->id]);
    Conversation::factory()->closed()->create();

    expect($bot->section())->toBe(ConversationSection::Automatic)
        ->and($waiting->section())->toBe(ConversationSection::Waiting)
        ->and($manual->section())->toBe(ConversationSection::Manual)
        ->and($group->section())->toBe(ConversationSection::Groups);

    $this->actingAs($this->user)
        ->get('/atendimentos')
        ->assertOk()
        ->assertInertia(function ($page) use ($bot, $waiting, $manual, $group) {
            $sections = collect($page->toArray()['props']['sections'])->keyBy('value');

            expect($sections->keys()->all())->toBe([
                'automatic', 'after_hours', 'waiting', 'manual', 'groups',
            ])
                ->and(array_column($sections['automatic']['conversations'], 'id'))->toBe([$bot->id])
                ->and(array_column($sections['waiting']['conversations'], 'id'))->toBe([$waiting->id])
                ->and(array_column($sections['manual']['conversations'], 'id'))->toBe([$manual->id])
                ->and(array_column($sections['groups']['conversations'], 'id'))->toBe([$group->id]);
        });
});

it('lists what arrived outside the business hours', function (): void {
    $parked = Conversation::factory()->create(['status' => ConversationStatus::AfterHours]);

    $this->actingAs($this->user)
        ->get('/atendimentos')
        ->assertInertia(function ($page) use ($parked) {
            $section = collect($page->toArray()['props']['sections'])->firstWhere('value', 'after_hours');

            expect($section['label'])->toBe('Fora de Hora')
                ->and(array_column($section['conversations'], 'id'))->toBe([$parked->id]);
        });
});

it('shows groups without asking for a filter', function (): void {
    $group = Conversation::factory()->create([
        'status' => ConversationStatus::Pending,
        'is_group' => true,
    ]);

    $this->actingAs($this->user)
        ->get('/atendimentos')
        ->assertInertia(fn ($page) => expect(conversationIdsIn($page->toArray()['props']['sections']))->toContain($group->id));
});

it('shows groups even to a user that only sees its own conversations', function (): void {
    $restricted = userFor($this->tenant, null, ['hides_other_conversations' => true]);
    $group = Conversation::factory()->create(['is_group' => true, 'assigned_user_id' => null]);

    Conversation::factory()->create(['assigned_user_id' => userFor($this->tenant)->id]);

    $this->actingAs($restricted)
        ->get('/atendimentos')
        ->assertInertia(fn ($page) => expect(conversationIdsIn($page->toArray()['props']['sections']))->toBe([$group->id]));
});

it('hides a conversation from the url when the user cannot see it in the inbox', function (): void {
    $restricted = userFor($this->tenant, null, ['hides_other_conversations' => true]);
    $other = Conversation::factory()->create(['assigned_user_id' => userFor($this->tenant)->id]);

    $this->actingAs($restricted)->get("/atendimentos/{$other->id}")->assertNotFound();
    $this->actingAs($restricted)->get("/atendimentos/{$other->id}?page=1")->assertNotFound();
    $this->actingAs($restricted)->post("/atendimentos/{$other->id}/encerrar")->assertNotFound();
    $this->actingAs($restricted)->post("/atendimentos/{$other->id}/assumir")->assertNotFound();

    expect($other->fresh()->closed_at)->toBeNull()
        ->and($other->fresh()->assigned_user_id)->toBe($other->assigned_user_id);
});

it('keeps every conversation reachable for whoever sees them all', function (): void {
    $other = Conversation::factory()->create(['assigned_user_id' => userFor($this->tenant)->id]);

    $this->actingAs($this->user)->get("/atendimentos/{$other->id}")->assertOk();
});

it('finds a contact by the nickname the team gave it', function (): void {
    $contact = Contact::factory()->create(['name' => 'Cliente 9999', 'nickname' => 'Padaria da Esquina']);
    $conversation = Conversation::factory()->create(['contact_id' => $contact->id]);

    Conversation::factory()->create();

    $this->actingAs($this->user)
        ->get('/atendimentos?search=Padaria')
        ->assertInertia(function ($page) use ($conversation) {
            $ids = conversationIdsIn($page->toArray()['props']['sections']);

            expect($ids)->toBe([$conversation->id]);
        });
});

it('shows the nickname in place of the name the provider reported', function (): void {
    $contact = Contact::factory()->create(['name' => 'Cliente 9999', 'nickname' => 'Padaria da Esquina']);
    $conversation = Conversation::factory()->create(['contact_id' => $contact->id]);

    $this->actingAs($this->user)
        ->get("/atendimentos/{$conversation->id}")
        ->assertInertia(fn ($page) => $page
            ->where('selected.contact.name', 'Padaria da Esquina')
            ->where('selected.contact.legal_name', 'Cliente 9999'));
});
