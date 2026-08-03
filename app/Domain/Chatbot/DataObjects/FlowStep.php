<?php

namespace App\Domain\Chatbot\DataObjects;

readonly class FlowStep
{
    public const ACTION_NEXT = 'next';

    public const ACTION_WAIT = 'wait';

    public const ACTION_STOP = 'stop';

    /**
     * @param  array<string, mixed>  $stateChanges
     */
    private function __construct(
        public string $action,
        public ?string $handle = null,
        public ?string $awaiting = null,
        public array $stateChanges = [],
    ) {}

    /**
     * @param  array<string, mixed>  $stateChanges
     */
    public static function next(?string $handle = null, array $stateChanges = []): self
    {
        return new self(self::ACTION_NEXT, handle: $handle, stateChanges: $stateChanges);
    }

    /**
     * @param  array<string, mixed>  $stateChanges
     */
    public static function wait(string $awaiting, array $stateChanges = []): self
    {
        return new self(self::ACTION_WAIT, awaiting: $awaiting, stateChanges: $stateChanges);
    }

    /**
     * @param  array<string, mixed>  $stateChanges
     */
    public static function stop(array $stateChanges = []): self
    {
        return new self(self::ACTION_STOP, stateChanges: $stateChanges);
    }
}
