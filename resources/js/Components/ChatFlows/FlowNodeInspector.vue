<script setup>
import { computed } from "vue";
import FormField from "../UI/FormField.vue";
import TextInput from "../UI/TextInput.vue";
import TextArea from "../UI/TextArea.vue";
import SelectInput from "../UI/SelectInput.vue";
import Toggle from "../UI/Toggle.vue";
import Button from "../UI/Button.vue";
import { Plus, X } from "lucide-vue-next";

const props = defineProps({
    node: { type: Object, required: true },
    queues: { type: Array, default: () => [] },
    objectives: { type: Array, default: () => [] },
    agents: { type: Array, default: () => [] },
    cards: { type: Array, default: () => [] },
});

const emit = defineEmits(["update"]);

const conditionFields = [
    { value: "mensagem", label: "Última Mensagem" },
    { value: "contato.nome", label: "Nome do Contato" },
    { value: "contato.telefone", label: "Telefone do Contato" },
    { value: "contato.email", label: "Email do Contato" },
    { value: "atendimento.canal", label: "Canal do Atendimento" },
];

const operators = [
    { value: "equals", label: "É Igual a" },
    { value: "not_equals", label: "É Diferente de" },
    { value: "contains", label: "Contém" },
    { value: "filled", label: "Está Preenchido" },
    { value: "empty", label: "Está Vazio" },
];

const noActionOptions = [
    { value: "close", label: "Finalizar Atendimento" },
    { value: "queue", label: "Enviar para um Setor" },
    { value: "none", label: "Não Fazer Nada" },
];

const queueOptions = computed(() =>
    props.queues.map((queue) => ({ value: queue.id, label: queue.name })),
);
const objectiveOptions = computed(() =>
    props.objectives.map((item) => ({ value: item.id, label: item.name })),
);
const agentOptions = computed(() =>
    props.agents.map((agent) => ({ value: agent.id, label: agent.name })),
);
const cardOptions = computed(() =>
    props.cards.map((card) => ({ value: card.id, label: card.name })),
);

const options = computed(() => props.node.data?.options ?? []);

const variableHint = "{{contato.nome}}";

function set(key, value) {
    emit("update", { ...props.node.data, [key]: value });
}

function setOption(index, value) {
    const next = options.value.map((option, current) =>
        current === index ? { ...option, label: value } : option,
    );

    set("options", next);
}

function addOption() {
    set("options", [
        ...options.value,
        {
            id: `op${options.value.length + 1}-${Date.now().toString(36)}`,
            label: "",
        },
    ]);
}

function removeOption(index) {
    set(
        "options",
        options.value.filter((_, current) => current !== index),
    );
}
</script>

<template>
    <div class="space-y-4">
        <FormField label="Nome do bloco">
            <TextInput
                :model-value="node.data?.label ?? ''"
                @update:model-value="set('label', $event)"
            />
        </FormField>

        <template v-if="node.type === 'message'">
            <FormField
                label="Cartão"
                hint="Com um cartão escolhido a mensagem abaixo é ignorada."
            >
                <SelectInput
                    :model-value="node.data?.card_id ?? null"
                    :options="cardOptions"
                    placeholder="Escrever a mensagem"
                    @update:model-value="set('card_id', $event)"
                />
            </FormField>

            <FormField
                v-if="!node.data?.card_id"
                label="Mensagem"
                :hint="`Use ${variableHint} para personalizar.`"
            >
                <TextArea
                    :model-value="node.data?.text ?? ''"
                    rows="4"
                    @update:model-value="set('text', $event)"
                />
            </FormField>
        </template>

        <template v-else-if="node.type === 'question'">
            <FormField label="Pergunta">
                <TextArea
                    :model-value="node.data?.text ?? ''"
                    rows="3"
                    @update:model-value="set('text', $event)"
                />
            </FormField>

            <FormField
                label="Salvar resposta como"
                hint="A chave fica disponível para os blocos seguintes."
            >
                <TextInput
                    :model-value="node.data?.save_as ?? ''"
                    @update:model-value="set('save_as', $event)"
                />
            </FormField>
        </template>

        <template v-else-if="node.type === 'menu'">
            <FormField label="Texto do menu">
                <TextArea
                    :model-value="node.data?.text ?? ''"
                    rows="3"
                    @update:model-value="set('text', $event)"
                />
            </FormField>

            <FormField
                group
                label="Opções"
                hint="Cada opção vira uma saída do bloco."
            >
                <div class="space-y-2">
                    <div
                        v-for="(option, index) in options"
                        :key="option.id"
                        class="flex items-center gap-2"
                    >
                        <span class="w-5 text-xs text-content-subtle">{{
                            index + 1
                        }}</span>
                        <TextInput
                            :model-value="option.label"
                            class="flex-1"
                            @update:model-value="setOption(index, $event)"
                        />
                        <button
                            type="button"
                            class="rounded-control p-1.5 text-content-subtle transition hover:bg-danger-soft hover:text-danger"
                            @click="removeOption(index)"
                        >
                            <X :size="14" />
                        </button>
                    </div>

                    <Button
                        size="sm"
                        variant="secondary"
                        :icon="Plus"
                        class="w-full justify-center"
                        @click="addOption"
                    >
                        Adicionar Opção
                    </Button>
                </div>
            </FormField>

            <FormField label="Mensagem para opção inválida">
                <TextInput
                    :model-value="node.data?.invalid_message ?? ''"
                    placeholder="Opção inválida. Escolha um número da lista."
                    @update:model-value="set('invalid_message', $event)"
                />
            </FormField>
        </template>

        <template v-else-if="node.type === 'condition'">
            <FormField label="Campo">
                <SelectInput
                    :model-value="node.data?.field ?? 'mensagem'"
                    :options="conditionFields"
                    @update:model-value="set('field', $event)"
                />
            </FormField>

            <FormField label="Operador">
                <SelectInput
                    :model-value="node.data?.operator ?? 'equals'"
                    :options="operators"
                    @update:model-value="set('operator', $event)"
                />
            </FormField>

            <FormField
                v-if="!['filled', 'empty'].includes(node.data?.operator)"
                label="Valor"
            >
                <TextInput
                    :model-value="node.data?.value ?? ''"
                    @update:model-value="set('value', $event)"
                />
            </FormField>
        </template>

        <template v-else-if="node.type === 'ai'">
            <FormField
                label="Objetivo de IA"
                hint="A IA assume a conversa e o fluxo encerra."
            >
                <SelectInput
                    :model-value="node.data?.ai_objective_id ?? null"
                    :options="objectiveOptions"
                    placeholder="Escolha um objetivo"
                    @update:model-value="set('ai_objective_id', $event)"
                />
            </FormField>
        </template>

        <template v-else-if="node.type === 'start'">
            <FormField
                label="Sem resposta após (minutos)"
                hint="Tempo que o chatbot espera o contato antes de agir sozinho."
            >
                <TextInput
                    :model-value="node.data?.no_action_minutes ?? 15"
                    type="number"
                    min="1"
                    @update:model-value="
                        set('no_action_minutes', Number($event))
                    "
                />
            </FormField>

            <FormField label="Ação sem resposta">
                <SelectInput
                    :model-value="node.data?.no_action ?? 'close'"
                    :options="noActionOptions"
                    @update:model-value="set('no_action', $event)"
                />
            </FormField>

            <FormField
                v-if="node.data?.no_action === 'queue'"
                label="Setor de destino"
                hint="O atendimento vai para o aguardando deste setor."
            >
                <SelectInput
                    :model-value="node.data?.no_action_service_queue_id ?? null"
                    :options="queueOptions"
                    placeholder="Setor padrão"
                    @update:model-value="
                        set('no_action_service_queue_id', $event)
                    "
                />
            </FormField>
        </template>

        <template v-else-if="node.type === 'queue'">
            <FormField
                label="Setor de destino"
                hint="Sem atendente o atendimento fica em aguardando para o setor."
            >
                <SelectInput
                    :model-value="node.data?.service_queue_id ?? null"
                    :options="queueOptions"
                    placeholder="Escolha um Setor"
                    @update:model-value="set('service_queue_id', $event)"
                />
            </FormField>

            <FormField
                label="Atendente"
                hint="Com um atendente do setor o atendimento vai direto para o manual."
            >
                <SelectInput
                    :model-value="node.data?.user_id ?? null"
                    :options="agentOptions"
                    placeholder="Qualquer atendente do setor"
                    @update:model-value="set('user_id', $event)"
                />
            </FormField>

            <FormField
                label="Cartão de transferência"
                hint="Enviado ao contato antes de transferir."
            >
                <SelectInput
                    :model-value="node.data?.card_id ?? null"
                    :options="cardOptions"
                    placeholder="Não enviar cartão"
                    @update:model-value="set('card_id', $event)"
                />
            </FormField>
        </template>

        <template v-else-if="node.type === 'close'">
            <FormField label="Mensagem de despedida">
                <TextArea
                    :model-value="node.data?.text ?? ''"
                    rows="3"
                    @update:model-value="set('text', $event)"
                />
            </FormField>

            <FormField label="Motivo do encerramento">
                <TextInput
                    :model-value="node.data?.reason ?? ''"
                    placeholder="Encerrado pelo chatbot."
                    @update:model-value="set('reason', $event)"
                />
            </FormField>
        </template>

        <template v-else-if="node.type === 'end'">
            <FormField label="Mensagem final">
                <TextArea
                    :model-value="node.data?.text ?? ''"
                    rows="3"
                    @update:model-value="set('text', $event)"
                />
            </FormField>

            <Toggle
                :model-value="Boolean(node.data?.close_conversation)"
                label="Encerrar atendimento"
                description="Finaliza a conversa ao chegar neste bloco."
                @update:model-value="set('close_conversation', $event)"
            />
        </template>

        <p v-else class="text-xs text-content-muted">
            Este bloco não possui configurações.
        </p>
    </div>
</template>
