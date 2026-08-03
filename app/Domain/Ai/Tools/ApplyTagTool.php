<?php

namespace App\Domain\Ai\Tools;

use App\Domain\Ai\Contracts\AiTool;
use App\Models\Conversation;
use App\Models\Tag;

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
        return [
            'type' => 'object',
            'properties' => [
                'tag_slug' => ['type' => 'string', 'description' => 'Identificador da tag.'],
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
            return 'Tag não encontrada.';
        }

        $conversation->tags()->syncWithoutDetaching([$tag->id]);

        return "Tag {$tag->name} aplicada.";
    }
}
