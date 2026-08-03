<?php

use App\Domain\Tenancy\TenantContext;
use App\Models\AiObjective;
use App\Models\Card;
use App\Models\ChannelConnection;
use App\Models\ChatFlow;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\QuickReply;
use App\Models\ServiceQueue;
use App\Models\Tag;
use App\Models\User;

beforeEach(function (): void {
    $this->tenant = tenant();
    $this->user = userFor($this->tenant);

    ServiceQueue::factory()->create(['is_default' => true]);
    Tag::factory()->create();
    QuickReply::factory()->create();
    Card::factory()->create();
    AiObjective::factory()->create();
    ChatFlow::factory()->create();
    ChannelConnection::factory()->create();
    Contact::factory()->create();
    Conversation::factory()->create();
});

it('shares the tenant in scope with every page', function (): void {
    app(TenantContext::class)->forget();

    $this->actingAs($this->user)
        ->get('/atendimentos')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('tenant.id', $this->tenant->id));
});

it('renders every page of the workspace', function (string $url, string $component): void {
    $this->actingAs($this->user)
        ->get($url)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component($component));
})->with([
    ['/painel', 'Dashboard'],
    ['/atendimentos', 'Conversations/Index'],
    ['/contatos', 'Contacts/Index'],
    ['/filas', 'ServiceQueues/Index'],
    ['/tags', 'Tags/Index'],
    ['/respostas-rapidas', 'QuickReplies/Index'],
    ['/cartoes', 'Cards/Index'],
    ['/conexoes', 'Connections/Index'],
    ['/objetivos-de-ia', 'AiObjectives/Index'],
    ['/objetivos-de-ia/novo', 'AiObjectives/Form'],
    ['/fluxos', 'ChatFlows/Index'],
    ['/usuarios', 'Users/Index'],
    ['/papeis', 'Roles/Index'],
    ['/relatorios', 'Reports/Index'],
    ['/auditoria', 'AuditLogs/Index'],
    ['/configuracoes', 'Settings/Edit'],
]);

it('renders the detail pages', function (): void {
    $contact = Contact::query()->first();
    $conversation = Conversation::query()->first();
    $connection = ChannelConnection::query()->first();
    $flow = ChatFlow::query()->first();
    $objective = AiObjective::query()->first();

    $this->actingAs($this->user)->get("/contatos/{$contact->id}")
        ->assertOk()->assertInertia(fn ($page) => $page->component('Contacts/Show'));

    $this->actingAs($this->user)->get("/atendimentos/{$conversation->id}")
        ->assertOk()->assertInertia(fn ($page) => $page->component('Conversations/Index')->has('selected'));

    $this->actingAs($this->user)->get("/conexoes/{$connection->id}")
        ->assertOk()->assertInertia(fn ($page) => $page->component('Connections/Show'));

    $this->actingAs($this->user)->get("/fluxos/{$flow->id}")
        ->assertOk()->assertInertia(fn ($page) => $page->component('ChatFlows/Builder')->has('node_types'));

    $this->actingAs($this->user)->get("/objetivos-de-ia/{$objective->id}/editar")
        ->assertOk()->assertInertia(fn ($page) => $page->component('AiObjectives/Form')->has('objective'));
});

it('renders the administration pages for a super admin', function (string $url, string $component): void {
    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->get($url)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component($component));
})->with([
    ['/admin/tenants', 'Admin/Tenants/Index'],
    ['/admin/auditoria', 'Admin/AuditLogs/Index'],
]);

it('sends guests to the login screen', function (): void {
    $this->get('/painel')->assertRedirect('/entrar');
    $this->get('/entrar')->assertOk()->assertInertia(fn ($page) => $page->component('Auth/Login'));
});

it('keeps a locked session on the lock screen', function (): void {
    $this->user->forceFill(['locked_at' => now()])->save();

    $this->actingAs($this->user)->get('/painel')->assertRedirect('/bloqueio');
    $this->actingAs($this->user)->get('/bloqueio')->assertOk()->assertInertia(fn ($page) => $page->component('Auth/Lock'));
});
