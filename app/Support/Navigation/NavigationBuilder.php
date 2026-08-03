<?php

namespace App\Support\Navigation;

use App\Models\User;

class NavigationBuilder
{
    /**
     * @return array<int, array{label: string, items: array<int, array<string, mixed>>}>
     */
    public function for(User $user): array
    {
        return collect($this->sections())
            ->map(fn (array $section): array => [
                'label' => $section['label'],
                'items' => collect($section['items'])
                    ->filter(fn (array $item): bool => $item['permission'] === null || $user->hasPermission($item['permission']))
                    ->values()
                    ->all(),
            ])
            ->filter(fn (array $section): bool => $section['items'] !== [])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array{label: string, items: array<int, array<string, mixed>>}>
     */
    private function sections(): array
    {
        return [
            [
                'label' => 'Atendimento',
                'items' => [
                    ['label' => 'Painel', 'href' => '/painel', 'icon' => 'dashboard', 'permission' => null],
                    ['label' => 'Atendimentos', 'href' => '/atendimentos', 'icon' => 'chat', 'permission' => 'conversations.view'],
                    ['label' => 'Contatos', 'href' => '/contatos', 'icon' => 'users', 'permission' => 'contacts.view'],
                    ['label' => 'Respostas Rápidas', 'href' => '/respostas-rapidas', 'icon' => 'bolt', 'permission' => 'quick-replies.view'],
                    ['label' => 'Cartões', 'href' => '/cartoes', 'icon' => 'card', 'permission' => 'cards.view'],
                ],
            ],
            [
                'label' => 'Automação',
                'items' => [
                    ['label' => 'Setores', 'href' => '/filas', 'icon' => 'queue', 'permission' => 'queues.view'],
                    ['label' => 'Objetivos de IA', 'href' => '/objetivos-de-ia', 'icon' => 'sparkles', 'permission' => 'ai-objectives.view'],
                    ['label' => 'Fluxos', 'href' => '/fluxos', 'icon' => 'flow', 'permission' => 'chat-flows.view'],
                    ['label' => 'Conexões', 'href' => '/conexoes', 'icon' => 'plug', 'permission' => 'connections.view'],
                ],
            ],
            [
                'label' => 'Organização',
                'items' => [
                    ['label' => 'Tags', 'href' => '/tags', 'icon' => 'tag', 'permission' => 'tags.view'],
                    ['label' => 'Usuários', 'href' => '/usuarios', 'icon' => 'user-plus', 'permission' => 'users.view'],
                    ['label' => 'Papéis', 'href' => '/papeis', 'icon' => 'shield', 'permission' => 'roles.view'],
                    ['label' => 'Relatórios', 'href' => '/relatorios', 'icon' => 'chart', 'permission' => 'reports.view'],
                    ['label' => 'Auditoria', 'href' => '/auditoria', 'icon' => 'audit', 'permission' => 'audit.view'],
                    ['label' => 'Configurações', 'href' => '/configuracoes', 'icon' => 'settings', 'permission' => 'settings.manage'],
                ],
            ],
        ];
    }
}
