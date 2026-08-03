<?php

namespace App\Domain\Messaging\DataObjects;

readonly class ContactIdentity
{
    public function __construct(
        public string $identifier,
        public ?string $name = null,
        public ?string $avatarUrl = null,
        public ?string $phone = null,
        public ?string $email = null,
        public bool $isGroup = false,
    ) {}
}
