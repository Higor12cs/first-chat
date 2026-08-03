<?php

namespace App\Http\Controllers;

use App\Http\Requests\TagRequest;
use App\Http\Resources\ServiceQueueResource;
use App\Http\Resources\TagResource;
use App\Models\ServiceQueue;
use App\Models\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TagController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Tags/Index', [
            'tags' => TagResource::collection(
                Tag::query()
                    ->withCount(['contacts', 'conversations'])
                    ->orderBy('name')
                    ->get()
            ),
            'queues' => ServiceQueueResource::collection(ServiceQueue::query()->ordered()->get()),
        ]);
    }

    public function store(TagRequest $request): RedirectResponse
    {
        Tag::create([
            ...$request->validated(),
            'slug' => Str::slug($request->string('name')),
        ]);

        return back()->with('success', 'Tag criada.');
    }

    public function update(TagRequest $request, Tag $tag): RedirectResponse
    {
        $tag->update($request->validated());

        return back()->with('success', 'Tag atualizada.');
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        $tag->delete();

        return back()->with('success', 'Tag excluída.');
    }
}
