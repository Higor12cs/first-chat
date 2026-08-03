<?php

namespace App\Services\Ai;

use App\Domain\Ai\Contracts\AiProvider;
use App\Domain\Ai\Exceptions\AiException;
use Illuminate\Contracts\Container\Container;

class AiManager
{
    /**
     * @var array<string, AiProvider>
     */
    private array $resolved = [];

    public function __construct(private readonly Container $container) {}

    public function provider(?string $provider = null): AiProvider
    {
        $key = $provider ?? (string) config('ai.default');

        return $this->resolved[$key] ??= $this->build($key);
    }

    /**
     * @return array<int, array{value: string, label: string, models: array<int, string>}>
     */
    public function options(): array
    {
        return collect(config('ai.providers', []))
            ->map(fn (array $config, string $key): array => [
                'value' => $key,
                'label' => $config['label'],
                'models' => $config['models'] ?? [],
                'configured' => filled($config['api_key'] ?? null),
            ])
            ->values()
            ->all();
    }

    private function build(string $key): AiProvider
    {
        $config = config("ai.providers.{$key}");

        if (! is_array($config)) {
            throw AiException::unknownProvider($key);
        }

        return $this->container->make($config['class'], [
            'key' => $key,
            'config' => $config,
        ]);
    }
}
