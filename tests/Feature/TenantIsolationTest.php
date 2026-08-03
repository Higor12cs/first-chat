<?php

use App\Domain\Tenancy\TenantContext;
use App\Models\Contact;
use App\Models\Conversation;

it('only returns records of the tenant in scope', function (): void {
    $first = tenant();
    Contact::factory()->count(2)->create();

    $second = tenant();
    Contact::factory()->create();

    expect(Contact::query()->count())->toBe(1);

    app(TenantContext::class)->set($first);

    expect(Contact::query()->count())->toBe(2);
    expect(Contact::query()->acrossTenants()->count())->toBe(3);
});

it('stamps the current tenant on new records', function (): void {
    $tenant = tenant();

    $contact = Contact::factory()->create(['tenant_id' => null]);

    expect($contact->tenant_id)->toBe($tenant->id);
});

it('hides conversations of another tenant from the inbox', function (): void {
    $other = tenant();
    $hidden = Conversation::factory()->create();

    $tenant = tenant();
    $visible = Conversation::factory()->create();
    $user = userFor($tenant);

    $this->actingAs($user)
        ->get('/atendimentos')
        ->assertOk()
        ->assertInertia(function ($page) use ($visible) {
            $page->component('Conversations/Index');

            expect(conversationIdsIn($page->toArray()['props']['sections']))->toBe([$visible->id]);
        });

    expect($hidden->tenant_id)->toBe($other->id);
});

it('returns 404 when opening a conversation from another tenant', function (): void {
    tenant();
    $foreign = Conversation::factory()->create();

    $tenant = tenant();
    $user = userFor($tenant);

    $this->actingAs($user)->get("/atendimentos/{$foreign->id}")->assertNotFound();
});
