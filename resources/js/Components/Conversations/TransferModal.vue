<script setup>
import { computed, watch } from "vue";
import { useForm } from "@inertiajs/vue3";
import { Bot, Clock, Headset } from "lucide-vue-next";
import Modal from "../UI/Modal.vue";
import Button from "../UI/Button.vue";
import FormField from "../UI/FormField.vue";
import SelectInput from "../UI/SelectInput.vue";

const props = defineProps({
    open: { type: Boolean, default: false },
    conversation: { type: Object, default: null },
    sections: { type: Array, default: () => [] },
    queues: { type: Array, default: () => [] },
    agents: { type: Array, default: () => [] },
    flows: { type: Array, default: () => [] },
    section: { type: String, default: "manual" },
    assignee: { type: String, default: null },
});

const emit = defineEmits(["close", "transferred"]);

const icons = { bot: Bot, clock: Clock, headset: Headset };

const form = useForm({
    section: "manual",
    service_queue_id: null,
    user_id: null,
    chat_flow_id: null,
    node_id: null,
});

const hasFlows = computed(() => props.flows.length > 0);

const targets = computed(() =>
    props.sections.map((section) => ({
        ...section,
        icon: icons[section.icon] ?? Headset,
        disabled: section.value === "automatic" && !hasFlows.value,
    })),
);

const activeTarget = computed(
    () => targets.value.find((target) => target.value === form.section) ?? null,
);

const queueOptions = computed(() =>
    props.queues.map((queue) => ({ value: queue.id, label: queue.name })),
);
const agentOptions = computed(() =>
    props.agents.map((agent) => ({ value: agent.id, label: agent.name })),
);
const flowOptions = computed(() =>
    props.flows.map((flow) => ({ value: flow.id, label: flow.name })),
);

const levelOptions = computed(() => {
    const flow = props.flows.find((item) => item.id === form.chat_flow_id);

    return (flow?.levels ?? []).map((level) => ({
        value: level.id,
        label: level.label,
    }));
});

function defaultQueueId() {
    const fallback =
        props.queues.find((queue) => queue.is_default) ?? props.queues[0];

    return props.conversation?.service_queue?.id ?? fallback?.id ?? null;
}

watch(
    () => props.open,
    (open) => {
        if (!open) {
            return;
        }

        const flow = props.flows[0] ?? null;

        form.defaults({
            section:
                props.section === "automatic" && !hasFlows.value
                    ? "waiting"
                    : props.section,
            service_queue_id: defaultQueueId(),
            user_id:
                props.assignee ?? props.conversation?.assigned_user?.id ?? null,
            chat_flow_id: flow?.id ?? null,
            node_id: flow?.levels?.[0]?.id ?? null,
        });

        form.reset();
        form.clearErrors();
    },
);

watch(
    () => form.chat_flow_id,
    (id) => {
        const flow = props.flows.find((item) => item.id === id);

        form.node_id = flow?.levels?.[0]?.id ?? null;
    },
);

function selectTarget(target) {
    if (!target.disabled) {
        form.section = target.value;
    }
}

function submit() {
    form.transform((data) => ({
        section: data.section,
        service_queue_id:
            data.section === "automatic" ? null : data.service_queue_id,
        user_id: data.section === "manual" ? data.user_id : null,
        chat_flow_id: data.section === "automatic" ? data.chat_flow_id : null,
        node_id: data.section === "automatic" ? data.node_id : null,
    })).put(`/atendimentos/${props.conversation.id}/transferencia`, {
        preserveScroll: true,
        preserveState: true,
        only: ["selected", "sections", "flash"],
        onSuccess: () => {
            emit("transferred", {
                section: form.section,
                userId: form.section === "manual" ? form.user_id : null,
            });
            emit("close");
        },
    });
}
</script>

<template>
    <Modal
        :open="open"
        title="Transferir Atendimento"
        description="Escolha para onde o atendimento vai."
        size="sm"
        @close="emit('close')"
    >
        <form id="transfer-form" class="space-y-4" @submit.prevent="submit">
            <FormField
                group
                label="Destino"
                :error="form.errors.section"
                required
            >
                <div class="grid gap-1.5 sm:grid-cols-3">
                    <button
                        v-for="target in targets"
                        :key="target.value"
                        type="button"
                        class="flex flex-col items-center gap-1 rounded-control border px-2 py-2.5 text-center transition"
                        :class="[
                            form.section === target.value
                                ? 'border-primary bg-primary-soft text-primary'
                                : 'border-border text-content-muted hover:bg-surface-hover',
                            target.disabled
                                ? 'cursor-not-allowed opacity-50'
                                : '',
                        ]"
                        :disabled="target.disabled"
                        @click="selectTarget(target)"
                    >
                        <component :is="target.icon" :size="16" />
                        <span class="text-xs font-medium">{{
                            target.label
                        }}</span>
                    </button>
                </div>
            </FormField>

            <p v-if="activeTarget" class="text-xs text-content-muted">
                {{ activeTarget.description }}
            </p>

            <template v-if="form.section !== 'automatic'">
                <FormField
                    label="Setor"
                    :error="form.errors.service_queue_id"
                    required
                >
                    <SelectInput
                        v-model="form.service_queue_id"
                        :options="queueOptions"
                        placeholder="Escolha um Setor"
                    />
                </FormField>

                <FormField
                    v-if="form.section === 'manual'"
                    label="Usuário"
                    :error="form.errors.user_id"
                    required
                >
                    <SelectInput
                        v-model="form.user_id"
                        :options="agentOptions"
                        placeholder="Escolha um Usuário"
                    />
                </FormField>
            </template>

            <template v-else>
                <FormField
                    label="Chatbot"
                    :error="form.errors.chat_flow_id"
                    required
                >
                    <SelectInput
                        v-model="form.chat_flow_id"
                        :options="flowOptions"
                        placeholder="Escolha um ChatBot"
                    />
                </FormField>

                <FormField
                    label="Nível"
                    :error="form.errors.node_id"
                    hint="O atendimento entra no fluxo a partir deste bloco."
                    required
                >
                    <SelectInput
                        v-model="form.node_id"
                        :options="levelOptions"
                        placeholder="Escolha um Nível"
                    />
                </FormField>
            </template>
        </form>

        <template #footer>
            <Button variant="secondary" @click="emit('close')">Cancelar</Button>
            <Button
                type="submit"
                form="transfer-form"
                :loading="form.processing"
                >Transferir</Button
            >
        </template>
    </Modal>
</template>
