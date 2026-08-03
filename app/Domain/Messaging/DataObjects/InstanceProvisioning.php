<?php

namespace App\Domain\Messaging\DataObjects;

readonly class InstanceProvisioning
{
    /**
     * @param  array<string, mixed>  $credentials
     */
    public function __construct(
        public array $credentials = [],
        public ?string $externalIdentifier = null,
    ) {}
}
