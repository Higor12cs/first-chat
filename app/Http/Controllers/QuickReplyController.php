<?php

namespace App\Http\Controllers;

use App\Http\Requests\QuickReplyRequest;
use App\Http\Resources\QuickReplyResource;
use App\Models\QuickReply;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class QuickReplyController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('QuickReplies/Index', [
            'filters' => $request->only(['search', 'category']),
            'quick_replies' => QuickReplyResource::collection(
                QuickReply::query()
                    ->with('user')
                    ->availableTo($request->user())
                    ->search($request->string('search')->value())
                    ->when($request->filled('category'), fn ($query) => $query->where('category', $request->string('category')))
                    ->orderByDesc('is_favorite')
                    ->orderBy('title')
                    ->get()
            ),
            'categories' => QuickReply::query()
                ->availableTo($request->user())
                ->whereNotNull('category')
                ->distinct()
                ->orderBy('category')
                ->pluck('category'),
        ]);
    }

    public function store(QuickReplyRequest $request): RedirectResponse
    {
        QuickReply::create([
            ...$request->validated(),
            'user_id' => $request->boolean('is_shared') ? null : $request->user()->id,
        ]);

        return back()->with('success', 'Resposta rápida criada.');
    }

    public function update(QuickReplyRequest $request, QuickReply $quickReply): RedirectResponse
    {
        $quickReply->update([
            ...$request->validated(),
            'user_id' => $request->boolean('is_shared') ? null : $quickReply->user_id ?? $request->user()->id,
        ]);

        return back()->with('success', 'Resposta rápida atualizada.');
    }

    public function favorite(QuickReply $quickReply): RedirectResponse
    {
        $quickReply->update(['is_favorite' => ! $quickReply->is_favorite]);

        return back();
    }

    public function destroy(QuickReply $quickReply): RedirectResponse
    {
        $quickReply->delete();

        return back()->with('success', 'Resposta rápida excluída.');
    }
}
