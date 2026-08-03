<?php

use App\Http\Controllers\Messaging\OutgoingMediaController;
use App\Http\Controllers\Webhooks\ConnectorWebhookController;
use Illuminate\Support\Facades\Route;

Route::prefix('/webhooks/conectores/{connection}')->name('webhooks.')->group(function (): void {
    Route::get('/', [ConnectorWebhookController::class, 'verify'])->name('connector.verify');
    Route::post('/', [ConnectorWebhookController::class, 'handle'])->name('connector');
});

Route::get('/anexos/{message}', OutgoingMediaController::class)
    ->middleware('signed:relative')
    ->name('messages.media.signed');
