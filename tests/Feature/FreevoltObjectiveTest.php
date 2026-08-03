<?php

use App\Models\AiObjective;
use App\Models\ServiceQueue;
use App\Models\Tag;
use Database\Seeders\FreevoltSeeder;

beforeEach(function (): void {
    tenant();

    $this->seed(FreevoltSeeder::class);

    $this->objective = AiObjective::query()->where('slug', 'qualificar-compra')->firstOrFail();
});

it('runs the sdr on a model that reliably calls its tools', function (): void {
    expect($this->objective->model)->toBe('gpt-5.4-mini')
        ->and(config('ai.pricing'))->toHaveKey('gpt-5.4-mini');
});

it('grants the sdr every tool its instructions name', function (): void {
    expect($this->objective->tools)
        ->toEqualCanonicalizing(['qualify_lead', 'transfer_to_queue', 'apply_tag', 'add_note', 'close_conversation']);

    foreach (['qualify_lead', 'transfer_to_queue', 'apply_tag', 'add_note', 'close_conversation'] as $tool) {
        expect($this->objective->system_prompt)->toContain($tool);
    }
});

it('points the transfer at a queue that exists', function (): void {
    expect($this->objective->system_prompt)->toContain('queue_slug "comercial"')
        ->and(ServiceQueue::query()->where('slug', 'comercial')->exists())->toBeTrue();
});

it('offers only tags the tenant actually has', function (): void {
    $slugs = ['pronto-para-comprar', 'comparando-preco', 'so-curiosidade', 'uso-em-camping', 'energia-de-emergencia'];

    foreach ($slugs as $slug) {
        expect($this->objective->system_prompt)->toContain($slug);
    }

    expect(Tag::query()->whereIn('slug', $slugs)->count())->toBe(count($slugs));
});

it('tells the sdr that announcing a close does not close anything', function (): void {
    expect($this->objective->system_prompt)->toContain('Dizer que vai encerrar não encerra');
});

it('keeps the sdr from interrogating the contact before helping', function (): void {
    expect($this->objective->system_prompt)
        ->toContain('Nunca peça compromisso de compra antes de ajudar')
        ->toContain('Nunca peça a potência dos aparelhos do contato');
});

it('leaves room for the conversation to explain before transferring', function (): void {
    expect($this->objective->max_turns)->toBeGreaterThanOrEqual(16)
        ->and($this->objective->closing_condition)->toContain('Dúvida sobre o produto nunca encerra');
});
