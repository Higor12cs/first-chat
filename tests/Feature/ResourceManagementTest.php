<?php

use App\Models\Card;
use App\Models\ChannelConnection;
use App\Models\ChatFlow;
use App\Models\Contact;
use App\Models\Role;
use App\Models\ServiceQueue;
use App\Models\Tag;
use App\Models\User;

beforeEach(function (): void {
    $this->tenant = tenant();
    $this->user = userFor($this->tenant);
});

it('creates a contact with tags', function (): void {
    $tag = Tag::factory()->create();

    $this->actingAs($this->user)
        ->post('/contatos', ['name' => 'João da Silva', 'phone' => '5511988887777', 'tags' => [$tag->id]])
        ->assertRedirect();

    $contact = Contact::query()->first();

    expect($contact->name)->toBe('João da Silva')
        ->and($contact->tags)->toHaveCount(1);
});

it('creates a queue with business hours and agents', function (): void {
    $agent = userFor($this->tenant);

    $this->actingAs($this->user)
        ->post('/filas', [
            'name' => 'Comercial',
            'color' => 'primary',
            'assignment_strategy' => 'round_robin',
            'business_hours' => ['1' => ['start' => '08:00', 'end' => '18:00']],
            'users' => [$agent->id],
        ])
        ->assertRedirect();

    $queue = ServiceQueue::query()->where('name', 'Comercial')->first();

    expect($queue->slug)->toBe('comercial')
        ->and($queue->assignment_strategy)->toBe('round_robin')
        ->and($queue->users)->toHaveCount(1)
        ->and($queue->business_hours)->toBe(['1' => ['start' => '08:00', 'end' => '18:00']]);
});

it('makes the first sector the default one', function (): void {
    $this->actingAs($this->user)
        ->post('/filas', ['name' => 'Comercial', 'color' => 'primary', 'assignment_strategy' => 'manual'])
        ->assertRedirect();

    expect(ServiceQueue::query()->where('name', 'Comercial')->first()->is_default)->toBeTrue();
});

it('keeps a single sector as the default one', function (): void {
    $previous = ServiceQueue::factory()->create(['is_default' => true]);

    $this->actingAs($this->user)
        ->post('/filas', [
            'name' => 'Suporte',
            'color' => 'primary',
            'assignment_strategy' => 'manual',
            'is_default' => true,
        ])
        ->assertRedirect();

    expect(ServiceQueue::query()->where('is_default', true)->count())->toBe(1)
        ->and($previous->fresh()->is_default)->toBeFalse();
});

it('refuses to delete the default sector while another one exists', function (): void {
    $default = ServiceQueue::factory()->create(['is_default' => true]);
    ServiceQueue::factory()->create();

    $this->actingAs($this->user)
        ->delete("/filas/{$default->id}")
        ->assertRedirect();

    expect($default->fresh())->not->toBeNull();
});

it('promotes another sector to default when the default one is gone', function (): void {
    $default = ServiceQueue::factory()->create(['is_default' => true]);
    $other = ServiceQueue::factory()->create();

    $default->forceFill(['is_default' => false])->save();

    $this->actingAs($this->user)
        ->delete("/filas/{$default->id}")
        ->assertRedirect();

    expect($other->fresh()->is_default)->toBeTrue();
});

it('routes a connection to a queue and a flow without touching the provider', function (): void {
    $connection = ChannelConnection::factory()->create(['driver' => 'uazapi']);
    $queue = ServiceQueue::factory()->create();

    $this->actingAs($this->user)
        ->put("/conexoes/{$connection->id}", [
            'name' => 'WhatsApp Comercial',
            'default_service_queue_id' => $queue->id,
            'is_active' => true,
        ])
        ->assertRedirect();

    $connection = $connection->fresh();

    expect($connection->name)->toBe('WhatsApp Comercial')
        ->and($connection->default_service_queue_id)->toBe($queue->id)
        ->and($connection->driver)->toBe('uazapi')
        ->and($connection->credential('token'))->toBe('instance-token');
});

it('ignores any credential a tenant tries to submit', function (): void {
    $connection = ChannelConnection::factory()->create(['driver' => 'uazapi']);

    $this->actingAs($this->user)
        ->put("/conexoes/{$connection->id}", [
            'name' => $connection->name,
            'driver' => 'meta_whatsapp',
            'credentials' => ['token' => 'invadido', 'admin_token' => 'invadido'],
        ])
        ->assertRedirect();

    $connection = $connection->fresh();

    expect($connection->driver)->toBe('uazapi')
        ->and($connection->credential('token'))->toBe('instance-token');
});

it('refuses to create or delete a connection from the workspace', function (): void {
    $connection = ChannelConnection::factory()->create();

    $this->actingAs($this->user)->post('/conexoes', ['name' => 'Extra'])->assertMethodNotAllowed();
    $this->actingAs($this->user)->delete("/conexoes/{$connection->id}")->assertMethodNotAllowed();

    expect(ChannelConnection::query()->count())->toBe(1);
});

it('never exposes the provider or its credentials to the interface', function (): void {
    ChannelConnection::factory()->create();

    $this->actingAs($this->user)
        ->get('/conexoes')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->missing('drivers')
            ->has('connections.0', fn ($connection) => $connection
                ->missing('credentials')
                ->missing('driver')
                ->missing('driver_label')
                ->missing('webhook_url')
                ->missing('last_error')
                ->etc()));
});

it('creates a role with the chosen permissions', function (): void {
    $this->actingAs($this->user)
        ->post('/papeis', ['name' => 'Atendente', 'permissions' => ['conversations.view', 'conversations.reply']])
        ->assertRedirect();

    $role = Role::query()->where('name', 'Atendente')->first();

    expect($role->permissions()->all())->toEqualCanonicalizing(['conversations.view', 'conversations.reply']);
});

it('refuses to change a protected role', function (): void {
    $role = Role::factory()->create(['is_locked' => true]);

    $this->actingAs($this->user)
        ->put("/papeis/{$role->id}", ['name' => 'Outro Nome'])
        ->assertForbidden();
});

it('creates a user with roles, queues and schedule', function (): void {
    $role = Role::factory()->create();
    $queue = ServiceQueue::factory()->create();

    $this->actingAs($this->user)
        ->post('/usuarios', [
            'name' => 'Ana Souza',
            'email' => 'ana@example.com',
            'password' => 'senha-secreta',
            'password_confirmation' => 'senha-secreta',
            'work_days' => [1, 2, 3, 4, 5],
            'work_starts_at' => '09:00',
            'work_ends_at' => '18:00',
            'roles' => [$role->id],
            'service_queues' => [$queue->id],
        ])
        ->assertRedirect();

    $created = User::query()->where('email', 'ana@example.com')->first();

    expect($created->belongsToTenant($this->tenant))->toBeTrue()
        ->and($created->roles)->toHaveCount(1)
        ->and($created->serviceQueues)->toHaveCount(1)
        ->and($created->work_days)->toBe([1, 2, 3, 4, 5]);
});

it('saves the flow definition drawn in the builder', function (): void {
    $flow = ChatFlow::factory()->create();

    $nodes = [
        ['id' => 'start', 'type' => 'start', 'position' => ['x' => 0, 'y' => 0], 'data' => []],
        ['id' => 'hello', 'type' => 'message', 'position' => ['x' => 260, 'y' => 0], 'data' => ['text' => 'Oi!']],
    ];

    $edges = [['id' => 'start-hello', 'source' => 'start', 'target' => 'hello', 'sourceHandle' => 'default']];

    $this->actingAs($this->user)
        ->put("/fluxos/{$flow->id}", [
            'name' => $flow->name,
            'nodes' => $nodes,
            'edges' => $edges,
            'is_active' => true,
        ])
        ->assertRedirect();

    expect($flow->fresh()->nodes)->toBe($nodes)
        ->and($flow->fresh()->edges)->toBe($edges);
});

it('creates a card', function (): void {
    $this->actingAs($this->user)
        ->post('/cartoes', ['name' => 'Fora de Hora', 'body' => 'Voltamos amanhã às 08:00.'])
        ->assertRedirect();

    expect(Card::query()->where('name', 'Fora de Hora')->first()->is_active)->toBeTrue();
});

it('refuses two cards with the same name', function (): void {
    Card::factory()->create(['name' => 'Fora de Hora']);

    $this->actingAs($this->user)
        ->post('/cartoes', ['name' => 'Fora de Hora', 'body' => 'Outro texto.'])
        ->assertSessionHasErrors('name');
});

it('saves the working schedule with intervals and exceptions', function (): void {
    $card = Card::factory()->create();

    $this->actingAs($this->user)
        ->put('/configuracoes', [
            'name' => $this->tenant->name,
            'timezone' => $this->tenant->timezone,
            'settings' => [
                'after_hours_enabled' => true,
                'after_hours_card_id' => $card->id,
                'business_hours' => [
                    '1' => [
                        ['start' => '08:00', 'end' => '11:30'],
                        ['start' => '13:00', 'end' => '18:00'],
                    ],
                ],
                'business_exceptions' => [
                    [
                        'type' => 'exception',
                        'name' => 'Treinamento',
                        'starts_on' => '2026-08-10',
                        'ends_on' => '2026-08-10',
                        'start' => '14:00',
                        'end' => '16:00',
                        'card_id' => $card->id,
                    ],
                ],
            ],
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $settings = $this->tenant->fresh()->settings;

    expect($settings['business_hours']['1'])->toHaveCount(2)
        ->and($settings['business_exceptions'][0]['name'])->toBe('Treinamento')
        ->and($settings['after_hours_card_id'])->toBe($card->id);
});

it('refuses an interval that ends before it starts', function (): void {
    $this->actingAs($this->user)
        ->put('/configuracoes', [
            'name' => $this->tenant->name,
            'timezone' => $this->tenant->timezone,
            'settings' => ['business_hours' => ['1' => [['start' => '18:00', 'end' => '08:00']]]],
        ])
        ->assertSessionHasErrors('settings.business_hours.1.0.end');
});

it('requires the window of a time exception', function (): void {
    $this->actingAs($this->user)
        ->put('/configuracoes', [
            'name' => $this->tenant->name,
            'timezone' => $this->tenant->timezone,
            'settings' => [
                'business_exceptions' => [
                    ['type' => 'exception', 'name' => 'Reunião', 'starts_on' => '2026-08-10', 'ends_on' => '2026-08-10'],
                ],
            ],
        ])
        ->assertSessionHasErrors('settings.business_exceptions.0.start');
});

it('keeps the tenant settings scoped to the current tenant', function (): void {
    $this->actingAs($this->user)
        ->put('/configuracoes', [
            'name' => 'Nova Razão Social',
            'timezone' => 'America/Manaus',
            'settings' => ['sign_messages' => true, 'auto_close_hours' => 24],
        ])
        ->assertRedirect();

    expect($this->tenant->fresh()->name)->toBe('Nova Razão Social')
        ->and($this->tenant->fresh()->settings['sign_messages'])->toBeTrue();
});
