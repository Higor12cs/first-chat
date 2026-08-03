<?php

namespace App\Http\Controllers\Conversations;

use App\Actions\Conversations\ApplyConversationTags;
use App\Domain\Tenancy\TenantContext;
use App\Http\Controllers\Controller;
use App\Models\Conversation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ConversationTagController extends Controller
{
    public function sync(Request $request, Conversation $conversation, ApplyConversationTags $applyTags): RedirectResponse
    {
        $validated = $request->validate([
            'tags' => ['array'],
            'tags.*' => ['uuid', Rule::exists('tags', 'id')->where('tenant_id', app(TenantContext::class)->id())],
        ]);

        $applyTags->handle($conversation, $validated['tags'] ?? []);

        return back()->with('success', 'Tags atualizadas.');
    }
}
