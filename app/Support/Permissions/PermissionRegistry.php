<?php

namespace App\Support\Permissions;

use Illuminate\Support\Collection;

class PermissionRegistry
{
    /**
     * @var Collection<string, Permission>|null
     */
    private static ?Collection $permissions = null;

    /**
     * @return Collection<string, Permission>
     */
    public static function all(): Collection
    {
        return self::$permissions ??= collect(self::definitions())
            ->keyBy(fn (Permission $permission): string => $permission->key);
    }

    /**
     * @return array<int, string>
     */
    public static function keys(): array
    {
        return self::all()->keys()->all();
    }

    /**
     * @return array<int, array{group: string, permissions: array<int, array<string, mixed>>}>
     */
    public static function grouped(): array
    {
        return self::all()
            ->groupBy(fn (Permission $permission): string => $permission->group)
            ->map(fn (Collection $permissions, string $group): array => [
                'group' => $group,
                'permissions' => $permissions->map->toArray()->values()->all(),
            ])
            ->values()
            ->all();
    }

    public static function forRoute(?string $routeName): ?Permission
    {
        if ($routeName === null) {
            return null;
        }

        return self::all()->first(
            fn (Permission $permission): bool => in_array($routeName, $permission->routes, true)
        );
    }

    public static function exists(string $key): bool
    {
        return self::all()->has($key);
    }

    /**
     * @return array<int, Permission>
     */
    private static function definitions(): array
    {
        return [
            new Permission('conversations.view', 'Visualizar Atendimentos', 'Atendimentos', ['conversations.index', 'conversations.show', 'conversations.messages.media']),
            new Permission('conversations.view-all', 'Visualizar Atendimentos de Todos', 'Atendimentos'),
            new Permission('conversations.reply', 'Responder Atendimentos', 'Atendimentos', ['conversations.messages.store', 'conversations.messages.resend', 'conversations.read', 'conversations.typing', 'conversations.take', 'conversations.store', 'conversations.contacts']),
            new Permission('conversations.messages.manage', 'Cancelar e Excluir Mensagens', 'Atendimentos', ['conversations.messages.cancel', 'conversations.messages.destroy']),
            new Permission('conversations.assign', 'Transferir Atendimentos', 'Atendimentos', ['conversations.transfer']),
            new Permission('conversations.close', 'Encerrar Atendimentos', 'Atendimentos', ['conversations.close', 'conversations.reopen']),
            new Permission('conversations.notes', 'Gerenciar Notas Internas', 'Atendimentos', ['conversations.notes.store', 'conversations.notes.destroy']),
            new Permission('conversations.tags', 'Aplicar Tags no Atendimento', 'Atendimentos', ['conversations.tags.sync']),

            new Permission('contacts.view', 'Visualizar Contatos', 'Contatos', ['contacts.index', 'contacts.show']),
            new Permission('contacts.create', 'Criar Contatos', 'Contatos', ['contacts.store']),
            new Permission('contacts.update', 'Editar Contatos', 'Contatos', ['contacts.update']),
            new Permission('contacts.delete', 'Excluir Contatos', 'Contatos', ['contacts.destroy']),

            new Permission('queues.view', 'Visualizar Setores', 'Setores', ['service-queues.index']),
            new Permission('queues.create', 'Criar Setores', 'Setores', ['service-queues.store']),
            new Permission('queues.update', 'Editar Setores', 'Setores', ['service-queues.update']),
            new Permission('queues.delete', 'Excluir Setores', 'Setores', ['service-queues.destroy']),

            new Permission('tags.view', 'Visualizar Tags', 'Tags', ['tags.index']),
            new Permission('tags.create', 'Criar Tags', 'Tags', ['tags.store']),
            new Permission('tags.update', 'Editar Tags', 'Tags', ['tags.update']),
            new Permission('tags.delete', 'Excluir Tags', 'Tags', ['tags.destroy']),

            new Permission('quick-replies.view', 'Visualizar Respostas Rápidas', 'Respostas Rápidas', ['quick-replies.index']),
            new Permission('quick-replies.create', 'Criar Respostas Rápidas', 'Respostas Rápidas', ['quick-replies.store']),
            new Permission('quick-replies.update', 'Editar Respostas Rápidas', 'Respostas Rápidas', ['quick-replies.update', 'quick-replies.favorite']),
            new Permission('quick-replies.delete', 'Excluir Respostas Rápidas', 'Respostas Rápidas', ['quick-replies.destroy']),

            new Permission('cards.view', 'Visualizar Cartões', 'Cartões', ['cards.index']),
            new Permission('cards.create', 'Criar Cartões', 'Cartões', ['cards.store']),
            new Permission('cards.update', 'Editar Cartões', 'Cartões', ['cards.update']),
            new Permission('cards.delete', 'Excluir Cartões', 'Cartões', ['cards.destroy']),

            new Permission('connections.view', 'Visualizar Conexões', 'Conexões', ['connections.index', 'connections.show']),
            new Permission('connections.update', 'Editar Conexões', 'Conexões', ['connections.update']),
            new Permission('connections.manage-session', 'Conectar e Desconectar', 'Conexões', ['connections.connect', 'connections.disconnect', 'connections.status']),

            new Permission('ai-objectives.view', 'Visualizar Objetivos de IA', 'Inteligência Artificial', ['ai-objectives.index']),
            new Permission('ai-objectives.create', 'Criar Objetivos de IA', 'Inteligência Artificial', ['ai-objectives.create', 'ai-objectives.store']),
            new Permission('ai-objectives.update', 'Editar Objetivos de IA', 'Inteligência Artificial', ['ai-objectives.edit', 'ai-objectives.update']),
            new Permission('ai-objectives.delete', 'Excluir Objetivos de IA', 'Inteligência Artificial', ['ai-objectives.destroy']),

            new Permission('chat-flows.view', 'Visualizar Fluxos', 'Chatbot', ['chat-flows.index', 'chat-flows.show']),
            new Permission('chat-flows.create', 'Criar Fluxos', 'Chatbot', ['chat-flows.store']),
            new Permission('chat-flows.update', 'Editar Fluxos', 'Chatbot', ['chat-flows.update']),
            new Permission('chat-flows.delete', 'Excluir Fluxos', 'Chatbot', ['chat-flows.destroy']),

            new Permission('users.view', 'Visualizar Usuários', 'Usuários', ['users.index']),
            new Permission('users.create', 'Criar Usuários', 'Usuários', ['users.store']),
            new Permission('users.update', 'Editar Usuários', 'Usuários', ['users.update']),
            new Permission('users.delete', 'Excluir Usuários', 'Usuários', ['users.destroy']),

            new Permission('roles.view', 'Visualizar Papéis', 'Papéis', ['roles.index']),
            new Permission('roles.create', 'Criar Papéis', 'Papéis', ['roles.store']),
            new Permission('roles.update', 'Editar Papéis', 'Papéis', ['roles.update']),
            new Permission('roles.delete', 'Excluir Papéis', 'Papéis', ['roles.destroy']),

            new Permission('reports.view', 'Visualizar Relatórios', 'Relatórios', ['reports.index']),
            new Permission('audit.view', 'Visualizar Auditoria', 'Auditoria', ['audit-logs.index']),
            new Permission('settings.manage', 'Gerenciar Configurações', 'Configurações', ['settings.edit', 'settings.update']),
        ];
    }
}
