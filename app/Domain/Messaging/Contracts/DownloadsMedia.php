<?php

namespace App\Domain\Messaging\Contracts;

use App\Domain\Messaging\DataObjects\MediaFile;

interface DownloadsMedia
{
    public function downloadMedia(string $externalId): ?MediaFile;
}
