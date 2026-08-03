<?php

namespace App\Support\Alerts;

use App\Domain\Messaging\Enums\ConnectionStatus;
use App\Domain\Messaging\Enums\MessageStatus;
use App\Models\ChannelConnection;
use App\Models\Message;
use App\Models\User;

class AlertBuilder
{
    private const FAILURE_WINDOW_MINUTES = 30;

    /**
     * @return array<int, array<string, mixed>>
     */
    public function for(?User $user): array
    {
        if ($user === null) {
            return [];
        }

        return [
            ...$this->connectionAlerts($user),
            ...$this->deliveryAlerts($user),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function connectionAlerts(User $user): array
    {
        $href = $user->hasPermission('connections.view') ? '/conexoes' : null;

        return ChannelConnection::query()
            ->where('is_active', true)
            ->where('status', '!=', ConnectionStatus::Connected->value)
            ->orderBy('name')
            ->get()
            ->map(fn (ChannelConnection $connection): array => [
                'id' => "connection-{$connection->id}",
                'level' => $connection->status === ConnectionStatus::Connecting ? 'warning' : 'danger',
                'title' => $connection->status === ConnectionStatus::Connecting
                    ? "{$connection->name} aguardando pareamento"
                    : "{$connection->name} desconectado",
                'message' => $connection->status === ConnectionStatus::Connecting
                    ? 'Leia o QR Code para conectar.'
                    : 'Novas mensagens não serão entregues até reconectar.',
                'href' => $href,
                'action' => $href === null ? null : 'Abrir Conexões',
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function deliveryAlerts(User $user): array
    {
        if (! $user->hasPermission('conversations.view')) {
            return [];
        }

        $failed = Message::query()
            ->where('status', MessageStatus::Failed->value)
            ->where('created_at', '>=', now()->subMinutes(self::FAILURE_WINDOW_MINUTES))
            ->count();

        if ($failed === 0) {
            return [];
        }

        return [[
            'id' => 'messages-failed',
            'level' => 'danger',
            'title' => $failed === 1 ? '1 mensagem não enviada' : "{$failed} mensagens não enviadas",
            'message' => 'Abra o atendimento para tentar de novo.',
            'href' => '/atendimentos',
            'action' => 'Abrir Atendimentos',
        ]];
    }
}
