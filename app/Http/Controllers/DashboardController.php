<?php

namespace App\Http\Controllers;

use App\Domain\Conversations\Enums\ConversationSection;
use App\Http\Resources\ConversationResource;
use App\Models\AiInteraction;
use App\Models\ChannelConnection;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    private const TREND_DAYS = 14;

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Dashboard', [
            'metrics' => [
                'open_conversations' => Conversation::query()->active()->count(),
                'pending_conversations' => Conversation::query()->inSection(ConversationSection::Waiting)->count(),
                'my_conversations' => Conversation::query()->where('assigned_user_id', $user->id)->active()->count(),
                'messages_today' => Message::query()->whereDate('created_at', today())->count(),
                'contacts' => Contact::query()->count(),
                'connections_online' => ChannelConnection::query()->connected()->count(),
                'ai_cost_month_micro_cents' => (int) AiInteraction::query()
                    ->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])
                    ->sum('cost_micro_cents'),
            ],
            'by_section' => $this->bySection(),
            'trend' => $this->trend(),
            'latest' => ConversationResource::collection(
                Conversation::query()
                    ->with(['contact', 'connection', 'serviceQueue', 'assignedUser', 'lastMessage'])
                    ->visibleTo($user)
                    ->active()
                    ->latest('last_message_at')
                    ->limit(8)
                    ->get()
            ),
        ]);
    }

    /**
     * @return array<int, array{label: string, total: int}>
     */
    private function bySection(): array
    {
        return collect(ConversationSection::cases())
            ->map(fn (ConversationSection $section): array => [
                'label' => $section->label(),
                'total' => Conversation::query()->inSection($section)->count(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{days: array<int, string>, inbound: array<int, int>, outbound: array<int, int>}
     */
    private function trend(): array
    {
        $from = today()->subDays(self::TREND_DAYS - 1);

        $rows = Message::query()
            ->where('created_at', '>=', $from)
            ->selectRaw('date(created_at) as day, direction, count(*) as total')
            ->groupByRaw('date(created_at), direction')
            ->get();

        $days = collect(range(0, self::TREND_DAYS - 1))
            ->map(fn (int $offset): Carbon => $from->copy()->addDays($offset));

        return [
            'days' => $days->map(fn (Carbon $day): string => $day->format('d/m'))->all(),
            'inbound' => $this->totalsFor($rows, $days, 'inbound'),
            'outbound' => $this->totalsFor($rows, $days, 'outbound'),
        ];
    }

    /**
     * @param  Collection<int, object>  $rows
     * @param  Collection<int, Carbon>  $days
     * @return array<int, int>
     */
    private function totalsFor(Collection $rows, Collection $days, string $direction): array
    {
        $totals = $rows
            ->where('direction', $direction)
            ->mapWithKeys(fn (object $row): array => [(string) $row->day => (int) $row->total]);

        return $days
            ->map(fn (Carbon $day): int => $totals->get($day->toDateString(), 0))
            ->all();
    }
}
