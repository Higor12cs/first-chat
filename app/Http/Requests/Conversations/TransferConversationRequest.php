<?php

namespace App\Http\Requests\Conversations;

use App\Domain\Conversations\Enums\ConversationSection;
use App\Domain\Tenancy\TenantContext;
use App\Models\ChatFlow;
use App\Models\Conversation;
use App\Models\ServiceQueue;
use App\Models\User;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! $this->conversation()->is_group;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'section' => ['required', Rule::in(ConversationSection::transferValues())],
            'service_queue_id' => [
                Rule::requiredIf(fn (): bool => $this->section()?->requiresQueue() === true),
                'nullable', 'uuid',
                Rule::exists('service_queues', 'id')->where('tenant_id', $tenantId)->where('is_active', true),
            ],
            'user_id' => [
                Rule::requiredIf(fn (): bool => $this->section()?->requiresAssignee() === true),
                'nullable', 'uuid',
                Rule::exists('tenant_user', 'user_id')->where('tenant_id', $tenantId)->where('is_active', true),
            ],
            'chat_flow_id' => [
                Rule::requiredIf(fn (): bool => $this->section()?->requiresChatFlow() === true),
                'nullable', 'uuid',
                Rule::exists('chat_flows', 'id')->where('tenant_id', $tenantId)->where('is_active', true),
            ],
            'node_id' => [
                Rule::requiredIf(fn (): bool => $this->section()?->requiresChatFlow() === true),
                'nullable', 'string', 'max:255',
            ],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->section() !== ConversationSection::Automatic || $validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->flow()->node($this->string('node_id')->value()) === null) {
                $validator->errors()->add('node_id', 'O nível escolhido não existe neste chatbot.');
            }
        });
    }

    public function section(): ?ConversationSection
    {
        return ConversationSection::tryFrom((string) $this->input('section'));
    }

    public function conversation(): Conversation
    {
        return $this->route('conversation');
    }

    public function queue(): ServiceQueue
    {
        return ServiceQueue::query()->findOrFail($this->string('service_queue_id')->value());
    }

    public function assignee(): User
    {
        return User::query()->findOrFail($this->string('user_id')->value());
    }

    public function flow(): ChatFlow
    {
        return ChatFlow::query()->findOrFail($this->string('chat_flow_id')->value());
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'section' => 'destino',
            'service_queue_id' => 'setor',
            'user_id' => 'usuário',
            'chat_flow_id' => 'chatbot',
            'node_id' => 'nível',
        ];
    }
}
