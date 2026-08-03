<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\Conversation;
use App\Support\Navigation\NavigationBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    private const LIMIT = 6;

    public function __invoke(Request $request, NavigationBuilder $navigation): JsonResponse
    {
        $user = $request->user();
        $term = $request->string('busca')->trim()->value();

        return response()->json([
            'conversations' => $this->conversations($request, $term),
            'contacts' => $this->contacts($request, $term),
            'modules' => $this->modules($navigation->for($user), $term),
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function conversations(Request $request, string $term): array
    {
        if (! $request->user()->hasPermission('conversations.view')) {
            return [];
        }

        return Conversation::query()
            ->with(['contact', 'assignedUser', 'serviceQueue'])
            ->visibleTo($request->user())
            ->active()
            ->when(filled($term), fn ($query) => $query->whereHas('contact', fn ($contact) => $contact->search($term)))
            ->orderByDesc('last_message_at')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Conversation $conversation): array => [
                'id' => $conversation->id,
                'label' => $conversation->contact?->displayName() ?? 'Sem contato',
                'hint' => $conversation->assignedUser?->name ?? $conversation->serviceQueue?->name,
                'href' => route('conversations.show', $conversation, absolute: false),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function contacts(Request $request, string $term): array
    {
        if (! $request->user()->hasPermission('contacts.view')) {
            return [];
        }

        return Contact::query()
            ->search($term)
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get()
            ->map(fn (Contact $contact): array => [
                'id' => $contact->id,
                'label' => $contact->displayName(),
                'hint' => $contact->phone,
                'href' => route('contacts.show', $contact, absolute: false),
            ])
            ->all();
    }

    /**
     * @param  array<int, array{label: string, items: array<int, array<string, mixed>>}>  $sections
     * @return array<int, array<string, mixed>>
     */
    private function modules(array $sections, string $term): array
    {
        return collect($sections)
            ->flatMap(fn (array $section): array => array_map(
                fn (array $item): array => [...$item, 'hint' => $section['label']],
                $section['items'],
            ))
            ->filter(fn (array $item): bool => blank($term)
                || str_contains($this->fold($item['label']), $this->fold($term)))
            ->map(fn (array $item): array => [
                'id' => $item['href'],
                'label' => $item['label'],
                'hint' => $item['hint'],
                'href' => $item['href'],
                'icon' => $item['icon'],
            ])
            ->values()
            ->all();
    }

    private function fold(string $value): string
    {
        return mb_strtolower(
            (string) preg_replace('/[^a-z0-9 ]/i', '', (string) iconv('UTF-8', 'ASCII//TRANSLIT', $value))
        );
    }
}
