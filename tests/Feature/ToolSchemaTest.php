<?php

use App\Domain\Ai\Tools\TransferToQueueTool;
use App\Models\ServiceQueue;

beforeEach(function (): void {
    tenant();
});

it('offers the model only queues that exist', function (): void {
    ServiceQueue::factory()->create(['slug' => 'comercial', 'name' => 'Comercial', 'is_active' => true]);
    ServiceQueue::factory()->create(['slug' => 'suporte', 'name' => 'Suporte', 'is_active' => true]);

    $schema = app(TransferToQueueTool::class)->schema();

    expect($schema['properties']['queue_slug']['enum'])->toEqualCanonicalizing(['comercial', 'suporte']);
});

it('keeps an inactive queue out of the options', function (): void {
    ServiceQueue::factory()->create(['slug' => 'comercial', 'is_active' => true]);
    ServiceQueue::factory()->create(['slug' => 'desativada', 'is_active' => false]);

    $schema = app(TransferToQueueTool::class)->schema();

    expect($schema['properties']['queue_slug']['enum'])->toBe(['comercial']);
});

it('names the queues in the description so the model can choose', function (): void {
    ServiceQueue::factory()->create(['slug' => 'financeiro', 'name' => 'Financeiro', 'is_active' => true]);

    $schema = app(TransferToQueueTool::class)->schema();

    expect($schema['properties']['queue_slug']['description'])->toContain('financeiro (Financeiro)');
});

it('still describes the field when the tenant has no queue yet', function (): void {
    $schema = app(TransferToQueueTool::class)->schema();

    expect($schema['properties']['queue_slug'])->not->toHaveKey('enum')
        ->and($schema['properties']['queue_slug']['type'])->toBe('string')
        ->and($schema['required'])->toBe(['queue_slug']);
});

it('shows each tenant only its own queues', function (): void {
    ServiceQueue::factory()->create(['slug' => 'comercial', 'is_active' => true]);

    $other = tenant();

    ServiceQueue::factory()->create(['slug' => 'exclusiva-do-outro', 'is_active' => true]);

    $schema = app(TransferToQueueTool::class)->schema();

    expect($schema['properties']['queue_slug']['enum'])->toBe(['exclusiva-do-outro'])
        ->and($other)->not->toBeNull();
});
