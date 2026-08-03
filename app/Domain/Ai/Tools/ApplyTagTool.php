<?php

namespace App\Domain\Ai\Tools;

use App\Domain\Ai\Contracts\AiTool;
use App\Models\Conversation;
use App\Models\Tag;
use Illuminate\Support\Collection;

class ApplyTagTool implements AiTool
{
    public function name(): string
    {
        return 'apply_tag';
    }

    public function label(): string
    {
        return 'Aplicar Tag';
    }

    public function description(): string
    {
        return 'Aplica uma tag ao atendimento para classificar o assunto tratado.';
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        $tags = $this->available();

        $target = ['type' => 'string', 'description' => 'Identificador da tag.'];

        if ($tags->isNotEmpty()) {
            $target['enum'] = $tags->pluck('slug')->all();
            $target['description'] = 'Tag a aplicar. Opções: '.$tags
                ->map(fn (Tag $tag): string => "{$tag->slug} ({$tag->name})")
                ->implode(', ').'.';
        }

        return [
            'type' => 'object',
            'properties' => [
                'tag_slug' => $target,
            ],
            'required' => ['tag_slug'],
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    public function execute(Conversation $conversation, array $arguments): string
    {
        $tag = Tag::query()->where('slug', $arguments['tag_slug'] ?? '')->first();

        if ($tag === null) {
            $options = $this->available()->pluck('slug');

            return $options->isEmpty()
                ? 'Nenhuma tag está cadastrada. Siga o atendimento sem classificar.'
                : 'Tag não encontrada. Use um destes identificadores: '.$options->implode(', ').'.';
        }

        $conversation->tags()->syncWithoutDetaching([$tag->id]);

        return "Tag {$tag->name} aplicada.";
    }

    /**
     * @return Collection<int, Tag>
     */
    private function available(): Collection
    {
        return Tag::query()->orderBy('name')->get();
    }
}
