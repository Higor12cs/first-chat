<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChannelConnectionRequest;
use App\Http\Resources\ChannelConnectionResource;
use App\Http\Resources\ChatFlowResource;
use App\Http\Resources\ServiceQueueResource;
use App\Models\ChannelConnection;
use App\Models\ChatFlow;
use App\Models\ServiceQueue;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ChannelConnectionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Connections/Index', [
            'connections' => ChannelConnectionResource::collection(
                ChannelConnection::query()->withCount('conversations')->orderBy('name')->get()
            ),
            'queues' => ServiceQueueResource::collection(ServiceQueue::query()->ordered()->get()),
            'flows' => ChatFlowResource::collection(ChatFlow::query()->orderBy('name')->get()),
        ]);
    }

    public function show(ChannelConnection $connection): Response
    {
        return Inertia::render('Connections/Show', [
            'connection' => ChannelConnectionResource::make($connection->loadCount('conversations')),
            'queues' => ServiceQueueResource::collection(ServiceQueue::query()->ordered()->get()),
            'flows' => ChatFlowResource::collection(ChatFlow::query()->orderBy('name')->get()),
        ]);
    }

    public function update(ChannelConnectionRequest $request, ChannelConnection $connection): RedirectResponse
    {
        $connection->update($request->validated());

        return back()->with('success', 'Conexão atualizada.');
    }
}
