<?php

namespace App\Http\Controllers;

use App\Http\Requests\ChatFlowRequest;
use App\Http\Resources\AiObjectiveResource;
use App\Http\Resources\CardResource;
use App\Http\Resources\ChatFlowResource;
use App\Http\Resources\ServiceQueueResource;
use App\Http\Resources\UserResource;
use App\Models\AiObjective;
use App\Models\Card;
use App\Models\ChatFlow;
use App\Models\ServiceQueue;
use App\Models\User;
use App\Services\Chatbot\FlowNodeRegistry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ChatFlowController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('ChatFlows/Index', [
            'flows' => ChatFlowResource::collection(ChatFlow::query()->orderBy('name')->get()),
        ]);
    }

    public function show(ChatFlow $chatFlow, FlowNodeRegistry $nodes): Response
    {
        return Inertia::render('ChatFlows/Builder', [
            'flow' => ChatFlowResource::make($chatFlow),
            'node_types' => $nodes->options(),
            'queues' => ServiceQueueResource::collection(ServiceQueue::query()->ordered()->get()),
            'objectives' => AiObjectiveResource::collection(AiObjective::query()->orderBy('name')->get()),
            'agents' => UserResource::collection(User::query()->active()->orderBy('name')->get()),
            'cards' => CardResource::collection(Card::query()->active()->orderBy('name')->get()),
        ]);
    }

    public function store(ChatFlowRequest $request): RedirectResponse
    {
        $flow = ChatFlow::create([
            ...$request->validated(),
            'slug' => Str::slug($request->string('name')),
            'nodes' => $request->input('nodes', $this->initialNodes()),
            'edges' => $request->input('edges', []),
        ]);

        return redirect()->route('chat-flows.show', $flow)->with('success', 'Fluxo criado.');
    }

    public function update(ChatFlowRequest $request, ChatFlow $chatFlow): RedirectResponse
    {
        $chatFlow->update($request->validated());

        return back()->with('success', 'Fluxo salvo.');
    }

    public function destroy(ChatFlow $chatFlow): RedirectResponse
    {
        $chatFlow->delete();

        return redirect()->route('chat-flows.index')->with('success', 'Fluxo excluído.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function initialNodes(): array
    {
        return [[
            'id' => 'start',
            'type' => 'start',
            'position' => ['x' => 80, 'y' => 80],
            'data' => [
                'no_action_minutes' => (int) config('chatbot.no_action_minutes'),
                'no_action' => (string) config('chatbot.no_action'),
            ],
        ]];
    }
}
