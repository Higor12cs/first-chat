<?php

namespace App\Http\Controllers;

use App\Domain\Conversations\Enums\ConversationSection;
use App\Models\AiInteraction;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $from = $request->date('from') ?? now()->subDays(29)->startOfDay();
        $to = $request->date('to') ?? now()->endOfDay();

        return Inertia::render('Reports/Index', [
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'summary' => [
                'conversations' => Conversation::query()->whereBetween('created_at', [$from, $to])->count(),
                'closed' => Conversation::query()->whereBetween('closed_at', [$from, $to])->count(),
                'messages_in' => Message::query()->whereBetween('created_at', [$from, $to])->where('direction', 'inbound')->count(),
                'messages_out' => Message::query()->whereBetween('created_at', [$from, $to])->where('direction', 'outbound')->count(),
                'ai_cost_micro_cents' => (int) AiInteraction::query()->whereBetween('created_at', [$from, $to])->sum('cost_micro_cents'),
                'first_response_minutes' => $this->averageFirstResponseMinutes($from, $to),
            ],
            'by_day' => $this->conversationsByDay($from, $to),
            'by_section' => $this->conversationsBySection($from, $to),
            'by_agent' => User::query()
                ->withCount(['conversations' => fn ($query) => $query->whereBetween('conversations.created_at', [$from, $to])])
                ->whereHas('conversations', fn ($query) => $query->whereBetween('conversations.created_at', [$from, $to]))
                ->orderByDesc('conversations_count')
                ->limit(10)
                ->get(['id', 'name'])
                ->map(fn (User $user): array => ['name' => $user->name, 'total' => $user->conversations_count]),
        ]);
    }

    /**
     * @return array<int, array{date: string, label: string, total: int}>
     */
    private function conversationsByDay(Carbon $from, Carbon $to): array
    {
        return Conversation::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('date(created_at) as date, count(*) as total')
            ->groupByRaw('date(created_at)')
            ->orderBy('date')
            ->get()
            ->map(fn ($row): array => [
                'date' => (string) $row->date,
                'label' => Carbon::parse((string) $row->date)->format('d/m'),
                'total' => (int) $row->total,
            ])
            ->all();
    }

    /**
     * @return array<int, array{label: string, total: int}>
     */
    private function conversationsBySection(Carbon $from, Carbon $to): array
    {
        return collect(ConversationSection::cases())
            ->map(fn (ConversationSection $section): array => [
                'label' => $section->label(),
                'total' => Conversation::query()
                    ->whereBetween('created_at', [$from, $to])
                    ->inSection($section)
                    ->count(),
            ])
            ->values()
            ->all();
    }

    private function averageFirstResponseMinutes(Carbon $from, Carbon $to): int
    {
        $average = Conversation::query()
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('first_response_at')
            ->select(DB::raw('avg(extract(epoch from (first_response_at - created_at))) as seconds'))
            ->value('seconds');

        return (int) round(((float) $average) / 60);
    }
}
