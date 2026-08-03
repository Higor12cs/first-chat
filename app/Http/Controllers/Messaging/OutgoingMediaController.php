<?php

namespace App\Http\Controllers\Messaging;

use App\Http\Controllers\Controller;
use App\Models\Message;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OutgoingMediaController extends Controller
{
    public function __invoke(string $message): StreamedResponse
    {
        $model = Message::query()->acrossTenants()->whereKey($message)->firstOrFail();

        abort_unless($model->mediaIsStored(), 404);

        $disk = Storage::disk('local');

        abort_unless($disk->exists((string) $model->media_url), 404);

        return $disk->response((string) $model->media_url, $model->media_name, [
            'Content-Type' => $model->media_mime_type ?: 'application/octet-stream',
        ]);
    }
}
