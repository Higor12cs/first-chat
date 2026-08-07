<script setup>
import { computed, ref } from "vue";
import { Head, Link, router, useForm, usePage } from "@inertiajs/vue3";
import {
    MessageCircle,
    Pencil,
    Plug,
    QrCode,
    RefreshCw,
    X,
} from "lucide-vue-next";
import { usePrivateChannel } from "../../Composables/useEchoChannel";
import PageHeader from "../../Components/UI/PageHeader.vue";
import Card from "../../Components/UI/Card.vue";
import Button from "../../Components/UI/Button.vue";
import Modal from "../../Components/UI/Modal.vue";
import FormField from "../../Components/UI/FormField.vue";
import TextInput from "../../Components/UI/TextInput.vue";
import SelectInput from "../../Components/UI/SelectInput.vue";
import Toggle from "../../Components/UI/Toggle.vue";
import Badge from "../../Components/UI/Badge.vue";
import EmptyState from "../../Components/UI/EmptyState.vue";
import { formatPhone, formatRelative } from "../../Utils/format";
import { usePermissions } from "../../Composables/usePermissions";

const props = defineProps({
    connections: { type: Array, default: () => [] },
    queues: { type: Array, default: () => [] },
    flows: { type: Array, default: () => [] },
});

const { can } = usePermissions();
const page = usePage();

const showForm = ref(false);
const editing = ref(null);

const queueOptions = computed(() =>
    props.queues.map((queue) => ({ value: queue.id, label: queue.name })),
);
const flowOptions = computed(() =>
    props.flows.map((flow) => ({ value: flow.id, label: flow.name })),
);

const form = useForm({
    name: "",
    default_service_queue_id: null,
    chat_flow_id: null,
    is_active: true,
});

function openForm(connection) {
    editing.value = connection;

    form.defaults({
        name: connection.name,
        default_service_queue_id: connection.default_service_queue_id,
        chat_flow_id: connection.chat_flow_id,
        is_active: connection.is_active,
    });

    form.reset();
    form.clearErrors();
    showForm.value = true;
}

function submit() {
    form.put(`/conexoes/${editing.value.id}`, {
        preserveScroll: true,
        onSuccess: () => (showForm.value = false),
    });
}

function connect(connection) {
    router.post(
        `/conexoes/${connection.id}/conectar`,
        {},
        { preserveScroll: true },
    );
}

function refreshStatus(connection) {
    router.post(
        `/conexoes/${connection.id}/status`,
        {},
        { preserveScroll: true },
    );
}

function disconnect(connection) {
    router.post(
        `/conexoes/${connection.id}/desconectar`,
        {},
        { preserveScroll: true },
    );
}

usePrivateChannel(
    () =>
        page.props.tenant
            ? `tenants.${page.props.tenant.id}.connections`
            : null,
    {
        "connector.status": () =>
            router.reload({
                only: ["connections"],
                preserveScroll: true,
                preserveState: true,
            }),
    },
);
</script>

<template>
    <Head title="Conexões" />

    <div class="space-y-5 p-4 lg:p-6">
        <PageHeader
            title="Conexões"
            subtitle="Canais disponíveis na sua conta e o estado de cada um."
        />

        <div
            v-if="connections.length"
            class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
        >
            <Card v-for="connection in connections" :key="connection.id">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-start gap-2.5">
                        <span
                            class="rounded-control bg-primary-soft p-2 text-primary"
                        >
                            <MessageCircle :size="18" />
                        </span>

                        <div class="min-w-0 space-y-1">
                            <Link
                                :href="`/conexoes/${connection.id}`"
                                class="block truncate text-sm font-semibold text-content hover:underline"
                            >
                                {{ connection.name }}
                            </Link>
                            <p class="truncate text-xs text-content-muted">
                                {{ connection.channel_label }}
                            </p>
                        </div>
                    </div>

                    <button
                        v-if="can('connections.update')"
                        type="button"
                        class="shrink-0 rounded-control p-1.5 text-content-subtle transition hover:bg-surface-hover hover:text-content"
                        @click="openForm(connection)"
                    >
                        <Pencil :size="16" />
                    </button>
                </div>

                <div class="mt-3 flex flex-wrap gap-1">
                    <Badge :color="connection.status_color" size="sm">{{
                        connection.status_label
                    }}</Badge>
                    <Badge v-if="!connection.is_active" color="danger" size="sm"
                        >Inativa</Badge
                    >
                    <Badge color="muted" size="sm"
                        >{{
                            connection.conversations_count ?? 0
                        }}
                        Atendimentos</Badge
                    >
                </div>

                <p
                    v-if="connection.external_identifier"
                    class="mt-2 truncate text-xs text-content-muted"
                >
                    {{ formatPhone(connection.external_identifier) }}
                </p>

                <p
                    v-if="connection.has_error"
                    class="mt-2 rounded-control bg-danger-soft px-2.5 py-1.5 text-xs text-danger"
                >
                    O canal não respondeu na última tentativa. Reconecte para
                    tentar de novo.
                </p>

                <p
                    v-if="connection.last_connected_at"
                    class="mt-2 text-[11px] text-content-subtle"
                >
                    Conectada {{ formatRelative(connection.last_connected_at) }}
                </p>

                <div
                    v-if="
                        connection.capabilities?.session &&
                        can('connections.manage-session')
                    "
                    class="mt-3 flex flex-wrap gap-2"
                >
                    <Button
                        size="sm"
                        variant="secondary"
                        :icon="QrCode"
                        @click="connect(connection)"
                        >Conectar</Button
                    >
                    <Button
                        size="sm"
                        variant="ghost"
                        :icon="RefreshCw"
                        @click="refreshStatus(connection)"
                        >Status</Button
                    >
                    <Button
                        size="sm"
                        variant="ghost"
                        :icon="X"
                        @click="disconnect(connection)"
                        >Desconectar</Button
                    >
                </div>
            </Card>
        </div>

        <Card v-else>
            <EmptyState
                :icon="Plug"
                title="Nenhuma Conexão"
                description="Os canais do seu plano aparecem aqui assim que a conta é liberada."
            />
        </Card>
    </div>

    <Modal
        :open="showForm"
        title="Editar Conexão"
        description="Defina como as mensagens deste canal entram no atendimento."
        @close="showForm = false"
    >
        <form id="connection-form" class="space-y-4" @submit.prevent="submit">
            <FormField label="Nome" :error="form.errors.name" required>
                <TextInput
                    v-model="form.name"
                    :invalid="Boolean(form.errors.name)"
                />
            </FormField>

            <div class="grid gap-4 sm:grid-cols-2">
                <FormField
                    label="Setor padrão"
                    :error="form.errors.default_service_queue_id"
                >
                    <SelectInput
                        v-model="form.default_service_queue_id"
                        :options="queueOptions"
                        placeholder="Sem Setor"
                    />
                </FormField>

                <FormField
                    label="Fluxo de entrada"
                    :error="form.errors.chat_flow_id"
                >
                    <SelectInput
                        v-model="form.chat_flow_id"
                        :options="flowOptions"
                        placeholder="Sem Fluxo"
                    />
                </FormField>
            </div>

            <Toggle v-model="form.is_active" label="Conexão Ativa" />
        </form>

        <template #footer>
            <Button variant="secondary" @click="showForm = false"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="connection-form"
                :loading="form.processing"
                >Salvar</Button
            >
        </template>
    </Modal>
</template>
