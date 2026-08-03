<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiInteraction;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class UsageController extends Controller
{
    public function index(Request $request): Response
    {
        $from = $request->date('from')?->startOfDay() ?? now()->startOfMonth();
        $to = $request->date('to')?->endOfDay() ?? now()->endOfDay();

        $messages = $this->messageTotals($from, $to);
        $ai = $this->aiTotals($from, $to);
        $conversations = $this->conversationTotals($from, $to);

        $rows = Tenant::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Tenant $tenant): array => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'status' => $tenant->status,
                'max_connections' => $tenant->limit('max_connections'),
                'messages_in' => (int) ($messages[$tenant->id]['inbound'] ?? 0),
                'messages_out' => (int) ($messages[$tenant->id]['outbound'] ?? 0),
                'messages_total' => (int) ($messages[$tenant->id]['inbound'] ?? 0) + (int) ($messages[$tenant->id]['outbound'] ?? 0),
                'max_monthly_messages' => $tenant->limit('max_monthly_messages'),
                'input_tokens' => (int) ($ai[$tenant->id]['input_tokens'] ?? 0),
                'output_tokens' => (int) ($ai[$tenant->id]['output_tokens'] ?? 0),
                'ai_interactions' => (int) ($ai[$tenant->id]['interactions'] ?? 0),
                'ai_cost_micro_cents' => (int) ($ai[$tenant->id]['cost_micro_cents'] ?? 0),
                'max_monthly_ai_cost_cents' => $tenant->limit('max_monthly_ai_cost_cents'),
                'conversations' => (int) ($conversations[$tenant->id] ?? 0),
            ])
            ->values();

        return Inertia::render('Admin/Usage/Index', [
            'filters' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'rows' => $rows,
            'totals' => [
                'messages_in' => $rows->sum('messages_in'),
                'messages_out' => $rows->sum('messages_out'),
                'input_tokens' => $rows->sum('input_tokens'),
                'output_tokens' => $rows->sum('output_tokens'),
                'ai_cost_micro_cents' => $rows->sum('ai_cost_micro_cents'),
                'conversations' => $rows->sum('conversations'),
            ],
        ]);
    }

    /**
     * @return array<string, array{inbound: int, outbound: int}>
     */
    private function messageTotals(Carbon $from, Carbon $to): array
    {
        return Message::query()
            ->acrossTenants()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('tenant_id, direction, count(*) as total')
            ->groupBy('tenant_id', 'direction')
            ->get()
            ->groupBy('tenant_id')
            ->map(fn ($rows): array => [
                'inbound' => (int) $rows->firstWhere('direction', 'inbound')?->total,
                'outbound' => (int) $rows->firstWhere('direction', 'outbound')?->total,
            ])
            ->all();
    }

    /**
     * @return array<string, array{input_tokens: int, output_tokens: int, cost_micro_cents: int, interactions: int}>
     */
    private function aiTotals(Carbon $from, Carbon $to): array
    {
        return AiInteraction::query()
            ->acrossTenants()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('tenant_id, count(*) as interactions, sum(input_tokens) as input_tokens, sum(output_tokens) as output_tokens, sum(cost_micro_cents) as cost_micro_cents')
            ->groupBy('tenant_id')
            ->get()
            ->keyBy('tenant_id')
            ->map(fn ($row): array => [
                'interactions' => (int) $row->interactions,
                'input_tokens' => (int) $row->input_tokens,
                'output_tokens' => (int) $row->output_tokens,
                'cost_micro_cents' => (int) $row->cost_micro_cents,
            ])
            ->all();
    }

    /**
     * @return array<string, int>
     */
    private function conversationTotals(Carbon $from, Carbon $to): array
    {
        return Conversation::query()
            ->acrossTenants()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('tenant_id, count(*) as total')
            ->groupBy('tenant_id')
            ->pluck('total', 'tenant_id')
            ->map(fn ($total): int => (int) $total)
            ->all();
    }
}
