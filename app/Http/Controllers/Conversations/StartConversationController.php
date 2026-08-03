<?php

namespace App\Http\Controllers\Conversations;

use App\Actions\Conversations\StartConversation;
use App\Domain\Messaging\Enums\ConnectionStatus;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\ContactResource;
use App\Models\ChannelConnection;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StartConversationController extends Controller
{
    public function store(Request $request, StartConversation $startConversation): RedirectResponse
    {
        $data = $request->validate([
            'contact_id' => [
                'nullable', 'uuid',
                Rule::exists('contacts', 'id')->where('tenant_id', app(TenantContext::class)->id()),
            ],
            'phone' => ['required_without:contact_id', 'nullable', 'string', 'max:20'],
            'name' => ['nullable', 'string', 'max:120'],
            'channel_connection_id' => [
                'nullable', 'uuid',
                Rule::exists('channel_connections', 'id')
                    ->where('tenant_id', app(TenantContext::class)->id())
                    ->where('is_active', true),
            ],
        ], [], [
            'phone' => 'número',
            'channel_connection_id' => 'canal',
        ]);

        $contact = isset($data['contact_id']) ? Contact::findOrFail($data['contact_id']) : null;
        $phone = $contact?->phone ?? ($data['phone'] ?? '');

        if (blank(preg_replace('/\D+/', '', $phone))) {
            throw ValidationException::withMessages(['phone' => 'Informe um número de WhatsApp.']);
        }

        $connection = $this->connection($data['channel_connection_id'] ?? null);

        if ($connection === null) {
            return back()->with('error', 'Nenhum canal disponível para iniciar o atendimento.');
        }

        $conversation = $startConversation->handle(
            connection: $connection,
            phone: $phone,
            name: $contact?->name ?? ($data['name'] ?? null),
            user: $request->user(),
        );

        return $this->redirectTo($conversation, $request->user());
    }

    private function redirectTo(Conversation $conversation, User $user): RedirectResponse
    {
        $owner = $conversation->assignedUser;

        if (Conversation::query()->visibleTo($user)->whereKey($conversation->getKey())->exists()) {
            return $owner === null || $owner->is($user)
                ? to_route('conversations.show', $conversation)
                : to_route('conversations.show', $conversation)
                    ->with('warning', "Este contato já está em atendimento por {$owner->name}.");
        }

        $queue = $conversation->serviceQueue?->name;

        return to_route('conversations.index')->with('warning', $owner === null
            ? 'Este contato já está em atendimento no setor '.($queue ?? 'de outro time').'. Peça a transferência para continuar.'
            : "Este contato já está em atendimento por {$owner->name}".($queue === null ? '' : " no setor {$queue}").'. Peça a transferência para continuar.');
    }

    public function contacts(Request $request): JsonResponse
    {
        $contacts = Contact::query()
            ->when($request->filled('busca'), fn ($query) => $query->search($request->string('busca')->value()))
            ->whereNotNull('phone')
            ->orderBy('name')
            ->limit(20)
            ->get();

        return response()->json(['data' => ContactResource::collection($contacts)->resolve()]);
    }

    private function connection(?string $connectionId): ?ChannelConnection
    {
        return ChannelConnection::query()
            ->where('is_active', true)
            ->when($connectionId !== null, fn ($query) => $query->whereKey($connectionId))
            ->orderByRaw('status = ? desc', [ConnectionStatus::Connected->value])
            ->orderBy('name')
            ->first();
    }
}
