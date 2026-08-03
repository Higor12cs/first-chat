<?php

namespace App\Domain\Ai\Transcribers;

use App\Domain\Ai\Contracts\AudioTranscriber;
use Illuminate\Support\Facades\Http;

class OpenAiTranscriber implements AudioTranscriber
{
    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(private readonly array $config) {}

    public function transcribe(string $url, ?string $mimeType = null): ?string
    {
        $audio = rescue(fn (): ?string => Http::timeout(60)->get($url)->body(), null, report: false);

        if (blank($audio)) {
            return null;
        }

        $response = Http::baseUrl(rtrim((string) $this->config['base_url'], '/'))
            ->withToken((string) ($this->config['api_key'] ?? ''))
            ->timeout(120)
            ->attach('file', $audio, $this->filename($mimeType))
            ->post('/audio/transcriptions', [
                'model' => (string) ($this->config['model'] ?? 'whisper-1'),
                'language' => (string) ($this->config['language'] ?? 'pt'),
            ]);

        if ($response->failed()) {
            return null;
        }

        $text = trim((string) $response->json('text'));

        return $text === '' ? null : $text;
    }

    private function filename(?string $mimeType): string
    {
        $extension = match (true) {
            $mimeType === null => 'ogg',
            str_contains($mimeType, 'mpeg') => 'mp3',
            str_contains($mimeType, 'mp4'), str_contains($mimeType, 'm4a') => 'm4a',
            str_contains($mimeType, 'wav') => 'wav',
            str_contains($mimeType, 'webm') => 'webm',
            default => 'ogg',
        };

        return "audio.{$extension}";
    }
}
