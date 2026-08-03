<?php

namespace App\Domain\Ai\Contracts;

interface AudioTranscriber
{
    public function transcribe(string $url, ?string $mimeType = null): ?string;
}
