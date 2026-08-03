<?php

namespace App\Domain\Messaging\DataObjects;

readonly class MediaFile
{
    public function __construct(
        public string $url,
        public ?string $mimeType = null,
        public ?string $name = null,
    ) {}
}
