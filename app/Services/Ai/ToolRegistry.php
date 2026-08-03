<?php

namespace App\Services\Ai;

use App\Domain\Ai\Contracts\AiTool;
use App\Domain\Ai\Tools\AddNoteTool;
use App\Domain\Ai\Tools\ApplyTagTool;
use App\Domain\Ai\Tools\CloseConversationTool;
use App\Domain\Ai\Tools\QualifyLeadTool;
use App\Domain\Ai\Tools\RequestHumanTool;
use App\Domain\Ai\Tools\TransferToQueueTool;
use Illuminate\Contracts\Container\Container;

class ToolRegistry
{
    /**
     * @var array<string, class-string<AiTool>>
     */
    private const TOOLS = [
        'transfer_to_queue' => TransferToQueueTool::class,
        'request_human' => RequestHumanTool::class,
        'close_conversation' => CloseConversationTool::class,
        'qualify_lead' => QualifyLeadTool::class,
        'apply_tag' => ApplyTagTool::class,
        'add_note' => AddNoteTool::class,
    ];

    public function __construct(private readonly Container $container) {}

    /**
     * @param  array<int, string>  $names
     * @return array<int, AiTool>
     */
    public function resolve(array $names): array
    {
        return collect($names)
            ->filter(fn (string $name): bool => isset(self::TOOLS[$name]))
            ->map(fn (string $name): AiTool => $this->container->make(self::TOOLS[$name]))
            ->values()
            ->all();
    }

    public function find(string $name): ?AiTool
    {
        return isset(self::TOOLS[$name]) ? $this->container->make(self::TOOLS[$name]) : null;
    }

    /**
     * @return array<int, array{value: string, label: string, description: string}>
     */
    public function options(): array
    {
        return collect(self::TOOLS)
            ->map(function (string $class, string $name): array {
                $tool = $this->container->make($class);

                return [
                    'value' => $name,
                    'label' => $tool->label(),
                    'description' => $tool->description(),
                ];
            })
            ->values()
            ->all();
    }
}
