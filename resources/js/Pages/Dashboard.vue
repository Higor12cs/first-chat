<script setup>
import { computed } from "vue";
import { Head, Link } from "@inertiajs/vue3";
import {
    Clock,
    Inbox,
    ListChecks,
    MessagesSquare,
    Plug,
    Send,
    Users,
    Workflow,
} from "lucide-vue-next";
import PageHeader from "../Components/UI/PageHeader.vue";
import Card from "../Components/UI/Card.vue";
import Chart from "../Components/UI/Chart.vue";
import Badge from "../Components/UI/Badge.vue";
import Avatar from "../Components/UI/Avatar.vue";
import EmptyState from "../Components/UI/EmptyState.vue";
import { formatRelative, formatUsdCents } from "../Utils/format";

const props = defineProps({
    metrics: { type: Object, required: true },
    by_section: { type: Array, default: () => [] },
    trend: {
        type: Object,
        default: () => ({ days: [], inbound: [], outbound: [] }),
    },
    latest: { type: Object, required: true },
});

const cards = computed(() => [
    {
        label: "Em Atendimento",
        value: props.metrics.open_conversations,
        icon: MessagesSquare,
        color: "primary",
    },
    {
        label: "Aguardando",
        value: props.metrics.pending_conversations,
        icon: Clock,
        color: "warning",
    },
    {
        label: "Meus Atendimentos",
        value: props.metrics.my_conversations,
        icon: Inbox,
        color: "info",
    },
    {
        label: "Mensagens Hoje",
        value: props.metrics.messages_today,
        icon: Send,
        color: "success",
    },
    {
        label: "Contatos",
        value: props.metrics.contacts,
        icon: Users,
        color: "muted",
    },
    {
        label: "Conexões Online",
        value: props.metrics.connections_online,
        icon: Plug,
        color: "success",
    },
]);

const sectionTotal = computed(() =>
    props.by_section.reduce((total, item) => total + item.total, 0),
);
const sectionLabels = computed(() =>
    props.by_section.map((item) => item.label),
);
const sectionSeries = computed(() =>
    props.by_section.map((item) => item.total),
);

const trendSeries = computed(() => [
    { name: "Recebidas", data: props.trend.inbound ?? [] },
    { name: "Enviadas", data: props.trend.outbound ?? [] },
]);

const hasTrend = computed(() =>
    trendSeries.value.some((serie) => serie.data.some((value) => value > 0)),
);

const shortcuts = [
    {
        label: "Abrir Atendimentos",
        href: "/atendimentos",
        icon: MessagesSquare,
    },
    { label: "Gerenciar Setores", href: "/filas", icon: ListChecks },
    { label: "Configurar Conexões", href: "/conexoes", icon: Plug },
    { label: "Editar Fluxos", href: "/fluxos", icon: Workflow },
];
</script>

<template>
    <Head title="Painel" />

    <div class="space-y-6 p-4 lg:p-6">
        <PageHeader
            title="Painel"
            subtitle="Acompanhe a operação de atendimento em tempo real."
        />

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <div
                v-for="card in cards"
                :key="card.label"
                class="flex items-center gap-3 rounded-card border border-border bg-surface p-4"
            >
                <span
                    class="flex h-10 w-10 items-center justify-center rounded-control"
                    :class="{
                        'bg-primary-soft text-primary':
                            card.color === 'primary',
                        'bg-warning-soft text-warning':
                            card.color === 'warning',
                        'bg-info-soft text-info': card.color === 'info',
                        'bg-success-soft text-success':
                            card.color === 'success',
                        'bg-surface-muted text-content-muted':
                            card.color === 'muted',
                    }"
                >
                    <component :is="card.icon" :size="18" />
                </span>

                <div>
                    <p class="text-xs text-content-muted">{{ card.label }}</p>
                    <p class="text-xl font-semibold text-content">
                        {{ card.value }}
                    </p>
                </div>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <Card
                title="Mensagens dos Últimos 14 Dias"
                description="Volume recebido e enviado por dia."
                class="lg:col-span-2"
            >
                <Chart
                    v-if="hasTrend"
                    type="area"
                    :series="trendSeries"
                    :categories="trend.days"
                    :height="260"
                    show-legend
                />

                <EmptyState
                    v-else
                    :icon="Send"
                    title="Sem Mensagens No Período"
                    description="Assim que o time começar a conversar o gráfico aparece aqui."
                />
            </Card>

            <Card
                title="Atendimentos por Seção"
                description="Como a fila está distribuída agora."
            >
                <Chart
                    v-if="sectionTotal > 0"
                    type="donut"
                    :series="sectionSeries"
                    :labels="sectionLabels"
                    :height="260"
                    show-legend
                />

                <EmptyState
                    v-else
                    title="Nenhum Atendimento Aberto"
                    description="Assim que chegarem mensagens elas aparecem aqui."
                />
            </Card>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <Card
                title="Custo de IA no Mês"
                description="Consumo acumulado dos objetivos ativos."
            >
                <p class="text-3xl font-semibold text-content">
                    {{ formatUsdCents(metrics.ai_cost_month_cents) }}
                </p>
                <p class="mt-1 text-xs text-content-muted">
                    Some limites por objetivo para controlar o gasto.
                </p>
            </Card>

            <Card
                title="Atalhos"
                description="Acesse as áreas mais usadas."
                class="lg:col-span-2"
            >
                <div class="grid gap-2 sm:grid-cols-2">
                    <Link
                        v-for="shortcut in shortcuts"
                        :key="shortcut.href"
                        :href="shortcut.href"
                        prefetch
                        class="flex items-center gap-2.5 rounded-control border border-border px-3 py-2 text-sm text-content transition hover:bg-surface-hover"
                    >
                        <component
                            :is="shortcut.icon"
                            :size="16"
                            class="text-content-muted"
                        />
                        {{ shortcut.label }}
                    </Link>
                </div>
            </Card>
        </div>

        <Card
            title="Últimos Atendimentos"
            description="Conversas com atividade recente."
            :padded="false"
        >
            <div v-if="latest.length > 0" class="divide-y divide-border">
                <Link
                    v-for="conversation in latest"
                    :key="conversation.id"
                    :href="`/atendimentos/${conversation.id}`"
                    class="flex items-center gap-3 px-4 py-3 transition hover:bg-surface-muted"
                >
                    <Avatar
                        :name="conversation.contact?.name"
                        :src="conversation.contact?.avatar_url"
                    />

                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-medium text-content">
                            {{ conversation.contact?.name }}
                        </p>
                        <p class="truncate text-xs text-content-muted">
                            {{
                                conversation.last_message?.body ??
                                "Sem mensagens."
                            }}
                        </p>
                    </div>

                    <div class="flex items-center gap-2">
                        <Badge :color="conversation.status_color">{{
                            conversation.section_label
                        }}</Badge>
                        <span
                            class="w-12 text-right text-xs text-content-subtle"
                        >
                            {{ formatRelative(conversation.last_message_at) }}
                        </span>
                    </div>
                </Link>
            </div>

            <EmptyState
                v-else
                title="Nenhum Atendimento Ainda"
                description="Conecte um canal para começar a receber mensagens."
            />
        </Card>
    </div>
</template>
