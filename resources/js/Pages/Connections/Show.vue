<script setup>
import { computed } from "vue";
import { Head, router, useForm, usePage } from "@inertiajs/vue3";
import { usePrivateChannel } from "../../Composables/useEchoChannel";
import PageHeader from "../../Components/UI/PageHeader.vue";
import Card from "../../Components/UI/Card.vue";
import Button from "../../Components/UI/Button.vue";
import Badge from "../../Components/UI/Badge.vue";
import {
    ArrowLeft,
    Check,
    KeyRound,
    QrCode,
    RefreshCw,
    Smartphone,
    X,
} from "lucide-vue-next";
import FormField from "../../Components/UI/FormField.vue";
import SelectInput from "../../Components/UI/SelectInput.vue";
import TextInput from "../../Components/UI/TextInput.vue";
import Toggle from "../../Components/UI/Toggle.vue";
import { formatDateTime, formatPhone } from "../../Utils/format";
import { usePermissions } from "../../Composables/usePermissions";

const props = defineProps({
    connection: { type: Object, required: true },
    queues: { type: Array, default: () => [] },
    flows: { type: Array, default: () => [] },
});

const { can } = usePermissions();
const page = usePage();

const capabilityLabels = {
    media: "Mídias",
    interactive_buttons: "Botões interativos",
    templates: "Templates",
    typing_indicator: "Indicador de digitação",
    read_receipts: "Confirmação de leitura",
    message_deletion: "Apagar para todos",
    groups: "Grupos",
    session: "Pareamento por QR Code",
};

const capabilities = computed(() =>
    Object.entries(capabilityLabels).map(([key, label]) => ({
        key,
        label,
        enabled: Boolean(props.connection.capabilities?.[key]),
    })),
);

const queueOptions = computed(() =>
    props.queues.map((queue) => ({ value: queue.id, label: queue.name })),
);
const flowOptions = computed(() =>
    props.flows.map((flow) => ({ value: flow.id, label: flow.name })),
);

const form = useForm({
    name: props.connection.name,
    default_service_queue_id: props.connection.default_service_queue_id,
    chat_flow_id: props.connection.chat_flow_id,
    is_active: props.connection.is_active,
});

function submit() {
    form.put(`/conexoes/${props.connection.id}`, { preserveScroll: true });
}

const pairing = useForm({ phone: "" });

function connect() {
    router.post(
        `/conexoes/${props.connection.id}/conectar`,
        {},
        { preserveScroll: true },
    );
}

function pairByPhone() {
    pairing.post(`/conexoes/${props.connection.id}/conectar`, {
        preserveScroll: true,
    });
}

function refreshStatus() {
    router.post(
        `/conexoes/${props.connection.id}/status`,
        {},
        { preserveScroll: true },
    );
}

function disconnect() {
    router.post(
        `/conexoes/${props.connection.id}/desconectar`,
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
                only: ["connection"],
                preserveScroll: true,
                preserveState: true,
            }),
    },
);
</script>

<template>
    <Head :title="connection.name" />

    <div class="space-y-5 p-4 lg:p-6">
        <PageHeader
            :title="connection.name"
            :subtitle="`Canal de ${connection.channel_label}.`"
        >
            <template #actions>
                <Button variant="secondary" :icon="ArrowLeft" href="/conexoes"
                    >Voltar</Button
                >
                <template
                    v-if="
                        connection.capabilities?.session &&
                        can('connections.manage-session')
                    "
                >
                    <Button :icon="QrCode" @click="connect">Conectar</Button>
                    <Button
                        variant="secondary"
                        :icon="RefreshCw"
                        @click="refreshStatus"
                        >Atualizar Status</Button
                    >
                    <Button variant="secondary" :icon="X" @click="disconnect"
                        >Desconectar</Button
                    >
                </template>
            </template>
        </PageHeader>

        <div class="grid gap-5 lg:grid-cols-3">
            <div class="space-y-5 lg:col-span-2">
                <Card title="Situação" description="Estado atual do canal.">
                    <div class="flex flex-wrap items-center gap-2">
                        <Badge :color="connection.status_color">{{
                            connection.status_label
                        }}</Badge>
                        <Badge :color="connection.channel_color">{{
                            connection.channel_label
                        }}</Badge>
                        <Badge v-if="!connection.is_active" color="danger"
                            >Inativa</Badge
                        >
                        <Badge color="muted"
                            >{{
                                connection.conversations_count ?? 0
                            }}
                            Atendimentos</Badge
                        >
                    </div>

                    <dl class="mt-4 grid gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="text-xs text-content-subtle">
                                Identificador
                            </dt>
                            <dd class="text-content">
                                {{
                                    formatPhone(
                                        connection.external_identifier,
                                    ) || "—"
                                }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs text-content-subtle">
                                Última Conexão
                            </dt>
                            <dd class="text-content">
                                {{
                                    formatDateTime(
                                        connection.last_connected_at,
                                    ) ?? "—"
                                }}
                            </dd>
                        </div>
                    </dl>

                    <p
                        v-if="connection.has_error"
                        class="mt-3 rounded-control bg-danger-soft px-3 py-2 text-xs text-danger"
                    >
                        O canal não respondeu na última tentativa. Use
                        "Conectar" para tentar de novo.
                    </p>

                    <div
                        v-if="connection.pair_code"
                        class="mt-4 flex flex-col items-center gap-2 rounded-card border border-border p-4"
                    >
                        <p class="text-xs text-content-muted">
                            No aplicativo, toque em Conectar Aparelho e escolha
                            Conectar Com Número De Telefone.
                        </p>
                        <p
                            class="font-mono text-2xl tracking-[0.3em] text-content"
                        >
                            {{ connection.pair_code }}
                        </p>
                        <p class="text-xs text-content-subtle">
                            O código vale por cinco minutos.
                        </p>
                    </div>

                    <div
                        v-else-if="connection.qr_code"
                        class="mt-4 flex flex-col items-center gap-2 rounded-card border border-border p-4"
                    >
                        <p class="text-xs text-content-muted">
                            Leia o QR Code no aplicativo para parear o número.
                        </p>
                        <img
                            :src="connection.qr_code"
                            alt="QR Code de pareamento"
                            class="h-56 w-56 rounded-control bg-white p-2"
                        />
                        <p class="text-xs text-content-subtle">
                            O código se renova sozinho a cada 20 segundos.
                        </p>
                    </div>
                </Card>

                <Card
                    v-if="
                        connection.capabilities?.pairing_code &&
                        can('connections.manage-session')
                    "
                    title="Parear por Número"
                    description="Alternativa ao QR Code para quem não consegue apontar a câmera."
                >
                    <form
                        class="flex flex-col gap-3 sm:flex-row sm:items-start"
                        @submit.prevent="pairByPhone"
                    >
                        <FormField
                            class="flex-1"
                            label="Número do WhatsApp"
                            :error="pairing.errors.phone"
                        >
                            <TextInput
                                v-model="pairing.phone"
                                placeholder="5511988887777"
                                inputmode="numeric"
                                :invalid="Boolean(pairing.errors.phone)"
                            />
                        </FormField>

                        <Button
                            type="submit"
                            variant="secondary"
                            :icon="KeyRound"
                            :loading="pairing.processing"
                            :disabled="!pairing.phone"
                            class="sm:mt-6"
                        >
                            Gerar Código
                        </Button>
                    </form>

                    <p
                        class="mt-2 flex items-center gap-1.5 text-xs text-content-subtle"
                    >
                        <Smartphone :size="13" />
                        Informe DDI e DDD, apenas dígitos. O código aparece
                        acima para ser digitado no aparelho.
                    </p>
                </Card>

                <Card
                    title="Configuração"
                    description="Destino das conversas que chegam por esta conexão."
                >
                    <form class="space-y-4" @submit.prevent="submit">
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

                        <Toggle
                            v-model="form.is_active"
                            label="Conexão Ativa"
                        />

                        <Button
                            v-if="can('connections.update')"
                            type="submit"
                            :loading="form.processing"
                            >Salvar</Button
                        >
                    </form>
                </Card>
            </div>

            <div class="space-y-5">
                <Card title="Recursos" description="O que este canal suporta.">
                    <ul class="space-y-2 text-sm">
                        <li
                            v-for="capability in capabilities"
                            :key="capability.key"
                            class="flex items-center gap-2"
                        >
                            <component
                                :is="capability.enabled ? Check : X"
                                :size="15"
                                :class="
                                    capability.enabled
                                        ? 'text-success'
                                        : 'text-content-subtle'
                                "
                            />
                            <span
                                :class="
                                    capability.enabled
                                        ? 'text-content'
                                        : 'text-content-subtle'
                                "
                            >
                                {{ capability.label }}
                            </span>
                        </li>
                    </ul>

                    <p
                        v-if="connection.capabilities?.reply_window_hours"
                        class="mt-3 text-xs text-content-muted"
                    >
                        Janela de resposta de
                        {{ connection.capabilities.reply_window_hours }} horas.
                    </p>

                    <div class="mt-3 flex flex-wrap gap-1">
                        <Badge
                            v-for="type in connection.capabilities
                                ?.message_types ?? []"
                            :key="type"
                            color="muted"
                            size="sm"
                        >
                            {{ type }}
                        </Badge>
                    </div>
                </Card>
            </div>
        </div>
    </div>
</template>
