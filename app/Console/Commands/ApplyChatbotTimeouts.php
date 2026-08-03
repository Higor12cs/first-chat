<?php

namespace App\Console\Commands;

use App\Actions\Chatbot\ApplyNoActionTimeout;
use App\Domain\Conversations\Enums\ConversationStatus;
use App\Domain\Tenancy\TenantContext;
use App\Models\Conversation;
use App\Models\Tenant;
use Illuminate\Console\Command;

class ApplyChatbotTimeouts extends Command
{
    protected $signature = 'chatbot:timeouts';

    protected $description = 'Resolve conversations the chatbot has been waiting on past their no action deadline';

    public function handle(TenantContext $context, ApplyNoActionTimeout $timeout): int
    {
        $resolved = 0;

        Tenant::query()->cursor()->each(function (Tenant $tenant) use ($context, $timeout, &$resolved): void {
            $context->run($tenant, function () use ($timeout, &$resolved): void {
                Conversation::query()
                    ->whereNotNull('no_action_at')
                    ->where('no_action_at', '<=', now())
                    ->whereIn('status', [ConversationStatus::Bot->value, ConversationStatus::Ai->value])
                    ->whereNull('assigned_user_id')
                    ->cursor()
                    ->each(function (Conversation $conversation) use ($timeout, &$resolved): void {
                        if ($timeout->handle($conversation)) {
                            $resolved++;
                        }
                    });
            });
        });

        $this->info("Atendimentos resolvidos por falta de resposta: {$resolved}.");

        return self::SUCCESS;
    }
}
