<script setup>
import { computed, ref, watch } from "vue";
import { Head, router } from "@inertiajs/vue3";
import { ChartColumn } from "lucide-vue-next";
import PageHeader from "../../../Components/UI/PageHeader.vue";
import Card from "../../../Components/UI/Card.vue";
import Chart from "../../../Components/UI/Chart.vue";
import DataTable from "../../../Components/UI/DataTable.vue";
import FormField from "../../../Components/UI/FormField.vue";
import TextInput from "../../../Components/UI/TextInput.vue";
import Badge from "../../../Components/UI/Badge.vue";
import EmptyState from "../../../Components/UI/EmptyState.vue";
import { formatUsdCents, formatUsdMicroCents } from "../../../Utils/format";

const props = defineProps({
    rows: { type: Array, default: () => [] },
    totals: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const from = ref(props.filters.from);
const to = ref(props.filters.to);

watch([from, to], () => {
    router.get("/admin/uso", { from: from.value, to: to.value }, {
        preserveState: true,
        replace: true,
        only: ["rows", "totals", "filters"],
    });
});

const columns = [
    { key: "name", label: "Tenant" },
    { key: "messages", label: "Mensagens" },
    { key: "tokens", label: "Tokens de IA" },
    { key: "cost", label: "Custo de IA", align: "right" },
];

const cards = computed(() => [
    { label: "Mensagens Recebidas", value: number(props.totals.messages_in) },
    { label: "Mensagens Enviadas", value: number(props.totals.messages_out) },
    { label: "Tokens de Entrada", value: number(props.totals.input_tokens) },
    { label: "Tokens de Saída", value: number(props.totals.output_tokens) },
    { label: "Atendimentos", value: number(props.totals.conversations) },
    { label: "Custo de IA", value: formatUsdMicroCents(props.totals.ai_cost_micro_cents) },
]);

function number(value) {
    return new Intl.NumberFormat("pt-BR").format(value ?? 0);
}

function share(used, limit) {
    if (!limit) {
        return null;
    }

    return Math.min(100, Math.round((used / limit) * 100));
}

const busiest = computed(() =>
    [...props.rows].sort((a, b) => b.messages_total - a.messages_total).slice(0, 10),
);

const messageSeries = computed(() => [
    { name: "Recebidas", data: busiest.value.map((row) => row.messages_in) },
    { name: "Enviadas", data: busiest.value.map((row) => row.messages_out) },
]);

const messageCategories = computed(() => busiest.value.map((row) => row.name));

const hasMessages = computed(() => busiest.value.some((row) => row.messages_total > 0));
</script>

<template>
    <Head title="Uso da Plataforma" />

    <div class="space-y-5 p-4 lg:p-6">
        <PageHeader title="Uso da Plataforma" subtitle="Consumo de mensagens e de IA por tenant no período.">
            <template #actions>
                <FormField label="De" class="w-40">
                    <TextInput v-model="from" type="date" />
                </FormField>

                <FormField label="Até" class="w-40">
                    <TextInput v-model="to" type="date" />
                </FormField>
            </template>
        </PageHeader>

        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            <div v-for="card in cards" :key="card.label" class="rounded-card border border-border bg-surface p-4">
                <p class="text-xs text-content-muted">{{ card.label }}</p>
                <p class="mt-1 text-xl font-semibold text-content">{{ card.value }}</p>
            </div>
        </div>

        <Card title="Tenants com Mais Mensagens" description="Os dez maiores volumes do período.">
            <Chart
                v-if="hasMessages"
                type="bar"
                :series="messageSeries"
                :categories="messageCategories"
                :height="320"
                horizontal
                stacked
                show-legend
            />

            <EmptyState v-else :icon="ChartColumn" title="Sem Movimento" description="Nenhuma mensagem no período." />
        </Card>

        <Card :padded="false">
            <DataTable :columns="columns" :rows="rows">
                <template #name="{ row }">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-content">{{ row.name }}</p>
                        <p class="truncate text-xs text-content-subtle">
                            {{ row.max_connections ?? "—" }} conexões · {{ number(row.conversations) }} atendimentos
                        </p>
                    </div>
                </template>

                <template #messages="{ row }">
                    <div class="space-y-0.5">
                        <p class="text-sm text-content">
                            {{ number(row.messages_in) }} recebidas · {{ number(row.messages_out) }} enviadas
                        </p>
                        <p v-if="row.max_monthly_messages" class="text-[11px] text-content-subtle">
                            {{ number(row.messages_total) }} de {{ number(row.max_monthly_messages) }}
                            ({{ share(row.messages_total, row.max_monthly_messages) }}%)
                        </p>
                    </div>
                </template>

                <template #tokens="{ row }">
                    <span class="text-sm text-content">
                        {{ number(row.input_tokens) }} entrada · {{ number(row.output_tokens) }} saída
                    </span>
                </template>

                <template #cost="{ row }">
                    <span class="flex items-center justify-end gap-2">
                        <Badge
                            v-if="row.max_monthly_ai_cost_cents"
                            :color="
                                row.ai_cost_micro_cents >= row.max_monthly_ai_cost_cents * 1_000_000
                                    ? 'danger'
                                    : 'muted'
                            "
                            size="sm"
                        >
                            limite {{ formatUsdCents(row.max_monthly_ai_cost_cents) }}
                        </Badge>
                        <span class="text-sm text-content">{{ formatUsdMicroCents(row.ai_cost_micro_cents) }}</span>
                    </span>
                </template>

                <template #empty>
                    <EmptyState :icon="ChartColumn" title="Sem Dados" description="Nenhum tenant cadastrado ainda." />
                </template>
            </DataTable>
        </Card>
    </div>
</template>
