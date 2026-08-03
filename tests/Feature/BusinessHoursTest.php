<?php

use App\Actions\Conversations\StartConversation;
use App\Actions\Messaging\ReceiveInboundMessage;
use App\Domain\Conversations\Enums\ConversationStatus;
use App\Domain\Conversations\Enums\MessageSource;
use App\Domain\Messaging\DataObjects\ContactIdentity;
use App\Domain\Messaging\DataObjects\InboundMessage;
use App\Domain\Messaging\Enums\MessageDirection;
use App\Events\Conversations\MessageReceived;
use App\Events\Conversations\MessageSent;
use App\Models\Card;
use App\Models\ChannelConnection;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\ServiceQueue;
use App\Models\Tenant;
use App\Services\Tenancy\BusinessHours;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;

function tenantWithHours(array $settings = []): Tenant
{
    return tenant(['timezone' => 'America/Sao_Paulo', 'settings' => [
        'after_hours_enabled' => true,
        'business_hours' => [
            '2' => [
                ['start' => '08:00', 'end' => '11:30'],
                ['start' => '13:00', 'end' => '18:00'],
            ],
        ],
        ...$settings,
    ]]);
}

function moment(string $expression): Carbon
{
    return Carbon::parse($expression, 'America/Sao_Paulo');
}

function afterHoursInbound(string $externalId): InboundMessage
{
    return new InboundMessage(
        externalId: $externalId,
        contact: new ContactIdentity(identifier: '5511999999999', name: 'Maria', phone: '5511999999999'),
        body: 'Olá, preciso de ajuda.',
    );
}

it('opens the account in every interval of the weekday', function (): void {
    $tenant = tenantWithHours();
    $hours = app(BusinessHours::class);

    expect($hours->isOpen($tenant, moment('2026-07-28 09:00')))->toBeTrue()
        ->and($hours->isOpen($tenant, moment('2026-07-28 12:00')))->toBeFalse()
        ->and($hours->isOpen($tenant, moment('2026-07-28 15:00')))->toBeTrue()
        ->and($hours->isOpen($tenant, moment('2026-07-28 19:00')))->toBeFalse();
});

it('still reads the single interval kept by older accounts', function (): void {
    $tenant = tenant(['timezone' => 'America/Sao_Paulo', 'settings' => [
        'business_hours' => ['2' => ['start' => '08:00', 'end' => '18:00']],
    ]]);

    $hours = app(BusinessHours::class);

    expect($hours->isOpen($tenant, moment('2026-07-28 09:00')))->toBeTrue()
        ->and($hours->isOpen($tenant, moment('2026-07-28 19:00')))->toBeFalse();
});

it('closes the whole day on a holiday', function (): void {
    $tenant = tenantWithHours([
        'business_exceptions' => [
            ['type' => 'holiday', 'name' => 'Feriado', 'starts_on' => '2026-07-28', 'ends_on' => '2026-07-28'],
        ],
    ]);

    $hours = app(BusinessHours::class);

    expect($hours->isOpen($tenant, moment('2026-07-28 09:00')))->toBeFalse()
        ->and($hours->isOpen($tenant, moment('2026-07-28 15:00')))->toBeFalse();
});

it('closes only the chosen window on an exception', function (): void {
    $tenant = tenantWithHours([
        'business_exceptions' => [
            [
                'type' => 'exception',
                'name' => 'Reunião',
                'starts_on' => '2026-07-28',
                'ends_on' => '2026-07-28',
                'start' => '09:00',
                'end' => '10:00',
            ],
        ],
    ]);

    $hours = app(BusinessHours::class);

    expect($hours->isOpen($tenant, moment('2026-07-28 09:30')))->toBeFalse()
        ->and($hours->isOpen($tenant, moment('2026-07-28 10:30')))->toBeTrue();
});

it('answers with the card of the exception in force', function (): void {
    $tenant = tenantWithHours();

    $default = Card::factory()->create(['name' => 'Padrão']);
    $holidayCard = Card::factory()->create(['name' => 'Feriado']);

    $tenant->update(['settings' => [
        ...$tenant->settings,
        'after_hours_card_id' => $default->id,
        'business_exceptions' => [
            [
                'type' => 'holiday',
                'name' => 'Feriado',
                'starts_on' => '2026-07-28',
                'ends_on' => '2026-07-28',
                'card_id' => $holidayCard->id,
            ],
        ],
    ]]);

    $hours = app(BusinessHours::class);

    expect($hours->cardFor($tenant, moment('2026-07-28 09:00'))?->id)->toBe($holidayCard->id)
        ->and($hours->cardFor($tenant, moment('2026-07-29 09:00'))?->id)->toBe($default->id);
});

it('sends the after hours card once when the conversation is parked', function (): void {
    Event::fake([MessageReceived::class, MessageSent::class]);

    $tenant = tenantWithHours();
    $card = Card::factory()->create(['body' => 'Estamos fechados, {{contato.nome}}.']);

    $tenant->update(['settings' => [...$tenant->settings, 'after_hours_card_id' => $card->id]]);

    ServiceQueue::factory()->create(['is_default' => true]);
    $connection = ChannelConnection::factory()->create();
    $action = app(ReceiveInboundMessage::class);

    Carbon::setTestNow(moment('2026-07-28 23:00'));

    $action->handle($connection, afterHoursInbound('noite-1'));
    $action->handle($connection, afterHoursInbound('noite-2'));

    $answers = Message::query()->where('direction', MessageDirection::Outbound)->get();

    expect($answers)->toHaveCount(1)
        ->and($answers->first()->body)->toBe('Estamos fechados, Maria.')
        ->and($answers->first()->source)->toBe(MessageSource::System);

    Carbon::setTestNow();
});

it('parks an open conversation that nobody owns when the shift ends', function (): void {
    Event::fake([MessageReceived::class, MessageSent::class]);

    $tenant = tenantWithHours();
    $queue = ServiceQueue::factory()->create(['is_default' => true, 'assignment_strategy' => 'manual']);
    $connection = ChannelConnection::factory()->create(['chat_flow_id' => null]);
    $action = app(ReceiveInboundMessage::class);

    Carbon::setTestNow(moment('2026-07-28 09:00'));
    $conversation = $action->handle($connection, afterHoursInbound('no-horario'))->conversation;

    expect($conversation->fresh()->status)->toBe(ConversationStatus::Pending);

    Carbon::setTestNow(moment('2026-07-28 23:00'));
    $action->handle($connection, afterHoursInbound('fora-de-hora'));

    expect($conversation->fresh()->status)->toBe(ConversationStatus::AfterHours)
        ->and(Conversation::query()->count())->toBe(1);

    Carbon::setTestNow();
});

it('leaves a conversation with an owner untouched after the shift', function (): void {
    Event::fake([MessageReceived::class, MessageSent::class]);

    $tenant = tenantWithHours();
    $agent = userFor($tenant);
    $connection = ChannelConnection::factory()->create(['chat_flow_id' => null]);
    $action = app(ReceiveInboundMessage::class);

    Carbon::setTestNow(moment('2026-07-28 09:00'));
    $conversation = $action->handle($connection, afterHoursInbound('no-horario'))->conversation;

    $conversation->forceFill([
        'status' => ConversationStatus::Open,
        'assigned_user_id' => $agent->id,
    ])->save();

    Carbon::setTestNow(moment('2026-07-28 23:00'));
    $action->handle($connection, afterHoursInbound('fora-de-hora'));

    expect($conversation->fresh()->status)->toBe(ConversationStatus::Open)
        ->and($conversation->fresh()->assigned_user_id)->toBe($agent->id);

    Carbon::setTestNow();
});

it('never parks a conversation the team started outside the shift', function (): void {
    Event::fake([MessageReceived::class, MessageSent::class]);

    $tenant = tenantWithHours();
    $card = Card::factory()->create();

    $tenant->update(['settings' => [...$tenant->settings, 'after_hours_card_id' => $card->id]]);

    ServiceQueue::factory()->create(['is_default' => true]);
    $connection = ChannelConnection::factory()->create();
    $agent = userFor($tenant);

    Carbon::setTestNow(moment('2026-07-28 23:00'));

    $conversation = app(StartConversation::class)->handle($connection, '5511988887777', 'Maria', $agent);

    expect($conversation->fresh()->status)->not->toBe(ConversationStatus::AfterHours)
        ->and($conversation->fresh()->assigned_user_id)->toBe($agent->id)
        ->and(Message::query()->count())->toBe(0);

    Carbon::setTestNow();
});

it('does not answer the account itself when it writes from the phone after hours', function (): void {
    Event::fake([MessageReceived::class, MessageSent::class]);

    $tenant = tenantWithHours();
    $card = Card::factory()->create();

    $tenant->update(['settings' => [...$tenant->settings, 'after_hours_card_id' => $card->id]]);

    ServiceQueue::factory()->create(['is_default' => true]);
    $connection = ChannelConnection::factory()->create();

    Carbon::setTestNow(moment('2026-07-28 23:00'));

    $echo = new InboundMessage(
        externalId: 'do-celular',
        contact: new ContactIdentity(identifier: '5511988887777', phone: '5511988887777'),
        body: 'Já respondo, Maria.',
        fromMe: true,
    );

    $conversation = app(ReceiveInboundMessage::class)->handle($connection, $echo)->conversation->fresh();

    expect($conversation->status)->not->toBe(ConversationStatus::AfterHours)
        ->and(Message::query()->pluck('body')->all())->toBe(['Já respondo, Maria.']);

    Carbon::setTestNow();
});

it('keeps groups out of the after hours section', function (): void {
    Event::fake([MessageReceived::class, MessageSent::class]);

    tenantWithHours();
    ServiceQueue::factory()->create(['is_default' => true]);
    $connection = ChannelConnection::factory()->create();

    Carbon::setTestNow(moment('2026-07-28 23:00'));

    $inbound = new InboundMessage(
        externalId: 'grupo-noite',
        contact: new ContactIdentity(identifier: '55119999@g.us', name: 'Equipe', isGroup: true),
        body: 'Alguém aí?',
    );

    $conversation = app(ReceiveInboundMessage::class)->handle($connection, $inbound)->conversation->fresh();

    expect($conversation->status)->not->toBe(ConversationStatus::AfterHours);

    Carbon::setTestNow();
});
