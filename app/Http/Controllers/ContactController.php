<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Http\Resources\ContactResource;
use App\Http\Resources\ConversationResource;
use App\Http\Resources\TagResource;
use App\Models\Contact;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function index(Request $request): Response
    {
        return Inertia::render('Contacts/Index', [
            'filters' => $request->only(['search', 'tag']),
            'contacts' => ContactResource::collection(
                Contact::query()
                    ->with(['tags', 'channels'])
                    ->withCount('conversations')
                    ->search($request->string('search')->value())
                    ->when($request->filled('tag'), fn ($query) => $query->whereHas(
                        'tags',
                        fn ($tags) => $tags->where('tags.id', $request->string('tag'))
                    ))
                    ->orderByRaw('coalesce(nickname, name)')
                    ->paginate(20)
                    ->withQueryString()
            ),
            'tags' => TagResource::collection(Tag::query()->orderBy('name')->get()),
        ]);
    }

    public function show(Contact $contact): Response
    {
        return Inertia::render('Contacts/Show', [
            'contact' => ContactResource::make($contact->load(['tags', 'channels.connection'])),
            'conversations' => ConversationResource::collection(
                $contact->conversations()
                    ->with(['connection', 'serviceQueue', 'assignedUser', 'lastMessage'])
                    ->latest('last_message_at')
                    ->limit(20)
                    ->get()
            ),
            'tags' => TagResource::collection(Tag::query()->orderBy('name')->get()),
        ]);
    }

    public function store(ContactRequest $request): RedirectResponse
    {
        $contact = Contact::create($request->validated());
        $contact->tags()->sync($request->input('tags', []));

        return back()->with('success', 'Contato criado.');
    }

    public function update(ContactRequest $request, Contact $contact): RedirectResponse
    {
        $contact->update($request->validated());
        $contact->tags()->sync($request->input('tags', []));

        return back()->with('success', 'Contato atualizado.');
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        $contact->delete();

        return back()->with('success', 'Contato excluído.');
    }
}
