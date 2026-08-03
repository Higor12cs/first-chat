<?php

namespace App\Http\Controllers\Conversations;

use App\Domain\Conversations\Enums\ConversationSection;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Http\Resources\ChannelConnectionResource;
use App\Http\Resources\ChatFlowSummaryResource;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\MessageResource;
use App\Http\Resources\QuickReplyResource;
use App\Http\Resources\ServiceQueueResource;
use App\Http\Resources\TagResource;
use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\ChannelConnection;
use App\Models\ChatFlow;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\QuickReply;
use App\Models\ServiceQueue;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConversationController extends Controller
{
    private const SECTION_LIMIT = 50;

    private const PAGE_SIZE = 40;

    public function index(Request $request, ?Conversation $conversation = null): Response
    {
        $user = $request->user();

        return Inertia::render('Conversations/Index', [
            'filters' => $request->only('search'),
            'sections' => $this->sections($request),
            'selected' => $conversation?->exists
                ? ConversationResource::make($conversation->load([
                    'contact.tags', 'contactChannel', 'connection', 'serviceQueue',
                    'assignedUser', 'aiObjective', 'tags', 'notes.user',
                ]))
                : null,
            'messages' => $conversation?->exists
                ? Inertia::scroll(fn () => MessageResource::collection($this->messagePage($request, $conversation)))
                : null,
            'timeline' => $conversation?->exists ? $this->timeline($conversation) : [],
            'connections' => ChannelConnectionResource::collection(
                ChannelConnection::query()->where('is_active', true)->orderBy('name')->get()
            ),
            'signature' => $user->signs_messages ?? (bool) (app(TenantContext::class)->get()?->settings['sign_messages'] ?? false),
            'queues' => ServiceQueueResource::collection(ServiceQueue::query()->where('is_active', true)->ordered()->get()),
            'agents' => UserResource::collection(User::query()->active()->orderBy('name')->get()),
            'flows' => ChatFlowSummaryResource::collection(ChatFlow::query()->active()->orderBy('name')->get()),
            'transfer_sections' => ConversationSection::transferOptions(),
            'visibility' => [
                'all' => $user->hasPermission('conversations.view-all') && ! $user->hides_other_conversations,
                'user_id' => $user->id,
                'queue_ids' => $user->serviceQueues()->pluck('service_queues.id'),
            ],
            'tags' => TagResource::collection(Tag::query()->orderBy('name')->get()),
            'quick_replies' => QuickReplyResource::collection(
                QuickReply::query()->availableTo($user)->orderByDesc('is_favorite')->orderBy('title')->get()
            ),
        ]);
    }

    public function show(Request $request, Conversation $conversation): Response
    {
        return $this->index($request, $conversation);
    }

    /**
     * @return LengthAwarePaginator<int, Message>
     */
    private function messagePage(Request $request, Conversation $conversation): LengthAwarePaginator
    {
        $query = $this->messagesQuery($conversation);
        $lastPage = max(1, (int) ceil((clone $query)->toBase()->getCountForPagination() / self::PAGE_SIZE));

        return $query->paginate(
            perPage: self::PAGE_SIZE,
            page: max(1, min($request->integer('page') ?: $lastPage, $lastPage)),
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function sections(Request $request): array
    {
        return array_map(function (ConversationSection $section) use ($request): array {
            $query = $this->query($request)->inSection($section);

            $conversations = (clone $query)
                ->orderByRaw('last_message_at IS NULL')
                ->orderByDesc('last_message_at')
                ->orderByDesc('id')
                ->limit(self::SECTION_LIMIT)
                ->get();

            return [
                'value' => $section->value,
                'label' => $section->label(),
                'description' => $section->description(),
                'color' => $section->color(),
                'icon' => $section->icon(),
                'total' => (clone $query)->count(),
                'unread' => (int) (clone $query)->sum('unread_count'),
                'conversations' => ConversationResource::collection($conversations)->resolve(),
            ];
        }, ConversationSection::cases());
    }

    private function messagesQuery(Conversation $conversation): Builder
    {
        return Message::query()
            ->whereIn('conversation_id', $this->historyIds($conversation))
            ->with(['user', 'replyTo.user'])
            ->oldest('id');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function timeline(Conversation $conversation): array
    {
        $conversations = $this->historyQuery($conversation)->get(['id', 'created_at', 'closed_at']);

        $events = $conversations->flatMap(fn (Conversation $item): array => array_values(array_filter([
            [
                'kind' => 'started',
                'label' => 'Atendimento Iniciado',
                'at' => $item->created_at?->toIso8601String(),
            ],
            $item->closed_at === null ? null : [
                'kind' => 'closed',
                'label' => 'Atendimento Finalizado',
                'at' => $item->closed_at->toIso8601String(),
            ],
        ])));

        return $events
            ->concat($this->auditEvents($conversations->pluck('id')->all()))
            ->filter(fn (array $event): bool => $event['at'] !== null)
            ->sortBy('at')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, string>  $conversationIds
     * @return array<int, array<string, mixed>>
     */
    private function auditEvents(array $conversationIds): array
    {
        return AuditLog::query()
            ->where('auditable_type', (new Conversation)->getMorphClass())
            ->whereIn('auditable_id', $conversationIds)
            ->whereIn('action', ['conversation.assigned', 'conversation.queued', 'conversation.transferred'])
            ->orderBy('created_at')
            ->get()
            ->map(fn (AuditLog $log): array => [
                'kind' => 'transfer',
                'label' => $this->auditLabel($log),
                'at' => $log->created_at?->toIso8601String(),
            ])
            ->all();
    }

    private function auditLabel(AuditLog $log): string
    {
        $queue = data_get($log->properties, 'queue');
        $assignee = data_get($log->properties, 'assignee');

        if ($log->action === 'conversation.transferred') {
            return $this->transferLabel($log, $queue, $assignee);
        }

        if ($log->action === 'conversation.queued') {
            return "Transferido para o Setor {$queue}";
        }

        return $assignee === null ? 'Atendimento Liberado' : "Transferido para {$assignee}";
    }

    private function transferLabel(AuditLog $log, ?string $queue, ?string $assignee): string
    {
        return match (ConversationSection::tryFrom((string) data_get($log->properties, 'section'))) {
            ConversationSection::Manual => "Transferido para {$assignee} no Setor {$queue}",
            ConversationSection::Automatic => 'Devolvido para o Chatbot '.data_get($log->properties, 'flow'),
            default => "Enviado para o Aguardando do Setor {$queue}",
        };
    }

    /**
     * @return array<int, string>
     */
    private function historyIds(Conversation $conversation): array
    {
        return $this->historyQuery($conversation)->pluck('id')->all();
    }

    private function historyQuery(Conversation $conversation): Builder
    {
        return Conversation::query()->when(
            $conversation->contact_channel_id === null,
            fn (Builder $query) => $query->whereKey($conversation->id),
            fn (Builder $query) => $query->where('contact_channel_id', $conversation->contact_channel_id),
        );
    }

    private function query(Request $request): Builder
    {
        return Conversation::query()
            ->with(['contact', 'contactChannel', 'connection', 'serviceQueue', 'assignedUser', 'tags', 'lastMessage'])
            ->visibleTo($request->user())
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $term = $request->string('search')->value();

                $query->whereHas('contact', fn (Builder $contact) => $contact->search($term));
            });
    }
}
