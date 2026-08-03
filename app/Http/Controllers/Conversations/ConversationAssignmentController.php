<?php

namespace App\Http\Controllers\Conversations;

use App\Actions\Conversations\TakeConversation;
use App\Actions\Conversations\TransferConversation;
use App\Domain\Conversations\Enums\ConversationSection;
use App\Http\Controllers\Controller;
use App\Http\Requests\Conversations\TransferConversationRequest;
use App\Models\Conversation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ConversationAssignmentController extends Controller
{
    public function transfer(
        TransferConversationRequest $request,
        Conversation $conversation,
        TransferConversation $transferConversation,
    ): RedirectResponse {
        $message = match ($request->section()) {
            ConversationSection::Manual => $this->toManual($request, $conversation, $transferConversation),
            ConversationSection::Waiting => $this->toWaiting($request, $conversation, $transferConversation),
            default => $this->toAutomatic($request, $conversation, $transferConversation),
        };

        return back()->with('success', $message);
    }

    public function take(Request $request, Conversation $conversation, TakeConversation $takeConversation): RedirectResponse
    {
        abort_if((bool) $conversation->is_group, 403);

        $takeConversation->handle($conversation, $request->user());

        return back()->with('success', 'Atendimento assumido.');
    }

    private function toManual(
        TransferConversationRequest $request,
        Conversation $conversation,
        TransferConversation $transferConversation,
    ): string {
        $queue = $request->queue();
        $assignee = $request->assignee();

        $transferConversation->toManual($conversation, $queue, $assignee, $request->user());

        return "Atendimento transferido para {$assignee->name} em {$queue->name}.";
    }

    private function toWaiting(
        TransferConversationRequest $request,
        Conversation $conversation,
        TransferConversation $transferConversation,
    ): string {
        $queue = $request->queue();

        $transferConversation->toWaiting($conversation, $queue);

        return "Atendimento enviado para o aguardando de {$queue->name}.";
    }

    private function toAutomatic(
        TransferConversationRequest $request,
        Conversation $conversation,
        TransferConversation $transferConversation,
    ): string {
        $flow = $request->flow();

        $transferConversation->toAutomatic($conversation, $flow, $request->string('node_id')->value());

        return "Atendimento devolvido para {$flow->name}.";
    }
}
