<?php

use App\Domain\Ai\Tools\ApplyTagTool;
use App\Domain\Ai\Tools\TransferToQueueTool;
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

it('grants the sdr every tool the business prompt asks it to use', function (): void {
    expect($this->objective->tools)
        ->toEqualCanonicalizing(['qualify_lead', 'transfer_to_queue', 'apply_tag', 'add_note', 'close_conversation']);
});

it('keeps system mechanics out of the business prompt', function (): void {
    $mechanics = [
        'qualify_lead', 'transfer_to_queue', 'apply_tag', 'add_note', 'close_conversation',
        'queue_slug', 'tag_slug', 'markdown', 'ferramenta',
    ];

    foreach ($mechanics as $term) {
        expect($this->objective->system_prompt)->not->toContain($term);
    }
});

it('points the transfer at a queue that exists', function (): void {
    expect(ServiceQueue::query()->where('slug', 'comercial')->exists())->toBeTrue()
        ->and(app(TransferToQueueTool::class)->schema()['properties']['queue_slug']['enum'])
        ->toContain('comercial');
});

it('leaves the tag catalog to the platform instead of hardcoding it in the prompt', function (): void {
    $slugs = ['pronto-para-comprar', 'comparando-preco', 'so-curiosidade', 'uso-em-camping', 'energia-de-emergencia'];

    expect(Tag::query()->whereIn('slug', $slugs)->count())->toBe(count($slugs))
        ->and($this->objective->tools)->toContain('apply_tag');

    $catalog = app(ApplyTagTool::class)->schema()['properties']['tag_slug']['enum'];

    foreach ($slugs as $slug) {
        expect($catalog)->toContain($slug);
    }
});

it('states the business rule for closing without naming the tool', function (): void {
    expect($this->objective->system_prompt)
        ->toContain('Fora desses casos, nunca encerre')
        ->toContain('Dúvida sobre o produto é motivo para explicar');
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
