<script setup>
import { computed, ref, watch } from "vue";
import { Head, router } from "@inertiajs/vue3";
import PageHeader from "../../Components/UI/PageHeader.vue";
import Card from "../../Components/UI/Card.vue";
import Chart from "../../Components/UI/Chart.vue";
import TextInput from "../../Components/UI/TextInput.vue";
import FormField from "../../Components/UI/FormField.vue";
import EmptyState from "../../Components/UI/EmptyState.vue";
import { formatUsdCents } from "../../Utils/format";

const props = defineProps({
    summary: { type: Object, required: true },
    by_day: { type: Array, default: () => [] },
    by_section: { type: Array, default: () => [] },
    by_agent: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const from = ref(props.filters.from);
const to = ref(props.filters.to);

const cards = computed(() => [
    { label: "Atendimentos Criados", value: props.summary.conversations },
    { label: "Atendimentos Encerrados", value: props.summary.closed },
    { label: "Mensagens Recebidas", value: props.summary.messages_in },
    { label: "Mensagens Enviadas", value: props.summary.messages_out },
    { label: "Custo de IA", value: formatUsdCents(props.summary.ai_cost_cents) },
    { label: "1ª Resposta (min)", value: props.summary.first_response_minutes },
]);

const daySeries = computed(() => [
    { name: "Atendimentos", data: props.by_day.map((day) => day.total) },
]);
const dayCategories = computed(() => props.by_day.map((day) => day.label));

const sectionSeries = computed(() => [
    { name: "Atendimentos", data: props.by_section.map((item) => item.total) },
]);
const sectionCategories = computed(() =>
    props.by_section.map((item) => item.label),
);

const agentSeries = computed(() => [
    { name: "Atendimentos", data: props.by_agent.map((agent) => agent.total) },
]);
const agentCategories = computed(() =>
    props.by_agent.map((agent) => agent.name),
);

function applyFilters() {
    router.get(
        "/relatorios",
        { from: from.value, to: to.value },
        { preserveState: true, replace: true },
    );
}

watch([from, to], applyFilters);
</script>

<template>
    <Head title="Relatórios" />

    <div class="space-y-5 p-4 lg:p-6">
        <PageHeader
            title="Relatórios"
            subtitle="Volume, seções e produtividade no período escolhido."
        >
            <template #actions>
                <FormField label="De">
                    <TextInput v-model="from" type="date" />
                </FormField>
                <FormField label="Até">
                    <TextInput v-model="to" type="date" />
                </FormField>
            </template>
        </PageHeader>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <div
                v-for="card in cards"
                :key="card.label"
                class="rounded-card border border-border bg-surface p-4"
            >
                <p class="text-xs text-content-muted">{{ card.label }}</p>
                <p class="mt-1 text-2xl font-semibold text-content">
                    {{ card.value }}
                </p>
            </div>
        </div>

        <Card
            title="Atendimentos por Dia"
            description="Distribuição diária no período."
        >
            <Chart
                v-if="by_day.length"
                type="area"
                :series="daySeries"
                :categories="dayCategories"
                :height="280"
            />
            <EmptyState
                v-else
                title="Sem Dados"
                description="Nenhum atendimento no período."
            />
        </Card>

        <div class="grid gap-5 lg:grid-cols-2">
            <Card
                title="Por Seção"
                description="Onde os atendimentos do período pararam."
            >
                <Chart
                    v-if="by_section.length"
                    type="bar"
                    :series="sectionSeries"
                    :categories="sectionCategories"
                    :height="280"
                    show-values
                />
                <EmptyState
                    v-else
                    title="Sem Dados"
                    description="Nenhum atendimento no período."
                />
            </Card>

            <Card
                title="Por Atendente"
                description="Os dez atendentes com mais conversas."
            >
                <Chart
                    v-if="by_agent.length"
                    type="bar"
                    :series="agentSeries"
                    :categories="agentCategories"
                    :height="280"
                    horizontal
                    show-values
                />
                <EmptyState
                    v-else
                    title="Sem Dados"
                    description="Nenhum atendimento atribuído no período."
                />
            </Card>
        </div>
    </div>
</template>
