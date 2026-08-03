<?php

namespace App\Http\Controllers;

use App\Http\Requests\CardRequest;
use App\Http\Resources\CardResource;
use App\Models\Card;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CardController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Cards/Index', [
            'filters' => $request->only(['search']),
            'cards' => CardResource::collection(
                Card::query()
                    ->search($request->string('search')->value())
                    ->orderBy('name')
                    ->get()
            ),
        ]);
    }

    public function store(CardRequest $request): RedirectResponse
    {
        Card::create($request->validated());

        return back()->with('success', 'Cartão criado.');
    }

    public function update(CardRequest $request, Card $card): RedirectResponse
    {
        $card->update($request->validated());

        return back()->with('success', 'Cartão atualizado.');
    }

    public function destroy(Card $card): RedirectResponse
    {
        $card->delete();

        return back()->with('success', 'Cartão excluído.');
    }
}
