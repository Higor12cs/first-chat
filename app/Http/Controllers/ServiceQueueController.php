<?php

namespace App\Http\Controllers;

use App\Actions\ServiceQueues\EnsureSingleDefaultQueue;
use App\Http\Requests\ServiceQueueRequest;
use App\Http\Resources\AiObjectiveResource;
use App\Http\Resources\ServiceQueueResource;
use App\Http\Resources\UserResource;
use App\Models\AiObjective;
use App\Models\ServiceQueue;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ServiceQueueController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('ServiceQueues/Index', [
            'queues' => ServiceQueueResource::collection(
                ServiceQueue::query()
                    ->with('users')
                    ->withCount('conversations')
                    ->ordered()
                    ->get()
            ),
            'agents' => UserResource::collection(User::query()->active()->orderBy('name')->get()),
            'objectives' => AiObjectiveResource::collection(AiObjective::query()->orderBy('name')->get()),
        ]);
    }

    public function store(ServiceQueueRequest $request, EnsureSingleDefaultQueue $ensureSingleDefault): RedirectResponse
    {
        $queue = ServiceQueue::create([
            ...$request->validated(),
            'slug' => Str::slug($request->string('name')),
        ]);

        $queue->users()->sync($request->input('users', []));

        $ensureSingleDefault->handle($queue);

        return back()->with('success', 'Setor criado.');
    }

    public function update(
        ServiceQueueRequest $request,
        ServiceQueue $serviceQueue,
        EnsureSingleDefaultQueue $ensureSingleDefault,
    ): RedirectResponse {
        $serviceQueue->update($request->validated());
        $serviceQueue->users()->sync($request->input('users', []));

        $ensureSingleDefault->handle($serviceQueue);

        return back()->with('success', 'Setor atualizado.');
    }

    public function destroy(ServiceQueue $serviceQueue, EnsureSingleDefaultQueue $ensureSingleDefault): RedirectResponse
    {
        if ($serviceQueue->is_default && ServiceQueue::query()->count() > 1) {
            return back()->with('error', 'Escolha outro setor como padrão antes de excluir este.');
        }

        $serviceQueue->delete();

        $ensureSingleDefault->handle();

        return back()->with('success', 'Setor excluído.');
    }
}
