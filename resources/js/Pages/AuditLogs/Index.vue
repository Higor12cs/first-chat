<script setup>
import { ScrollText } from "lucide-vue-next";
import { computed, ref, watch } from "vue";
import { Head, router } from "@inertiajs/vue3";
import PageHeader from "../../Components/UI/PageHeader.vue";
import Card from "../../Components/UI/Card.vue";
import DataTable from "../../Components/UI/DataTable.vue";
import Pagination from "../../Components/UI/Pagination.vue";
import SelectInput from "../../Components/UI/SelectInput.vue";
import Badge from "../../Components/UI/Badge.vue";
import EmptyState from "../../Components/UI/EmptyState.vue";
import { formatDateTime } from "../../Utils/format";

const props = defineProps({
    logs: { type: Object, required: true },
    actions: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const action = ref(props.filters.action ?? null);

const columns = [
    { key: "created_at", label: "Data" },
    { key: "action", label: "Ação" },
    { key: "user", label: "Usuário" },
    { key: "auditable", label: "Registro" },
    { key: "properties", label: "Detalhes" },
];

const actionOptions = computed(() => props.actions.map((item) => ({ value: item, label: item })));

watch(action, () => {
    router.get("/auditoria", { action: action.value || undefined }, {
        preserveState: true,
        replace: true,
        only: ["logs", "filters"],
    });
});

function summarize(properties) {
    if (!properties || Object.keys(properties).length === 0) {
        return "—";
    }

    return Object.entries(properties)
        .map(([key, value]) => `${key}: ${typeof value === "object" ? JSON.stringify(value) : value}`)
        .join(" · ");
}
</script>

<template>
    <Head title="Auditoria" />

    <div class="space-y-5 p-4 lg:p-6">
        <PageHeader title="Auditoria" subtitle="Registro das ações executadas dentro da sua operação.">
            <template #actions>
                <SelectInput v-model="action" :options="actionOptions" placeholder="Todas as ações" />
            </template>
        </PageHeader>

        <Card :padded="false">
            <DataTable :columns="columns" :rows="logs.data">
                <template #created_at="{ row }">
                    <span class="whitespace-nowrap text-xs text-content-muted">{{ formatDateTime(row.created_at) }}</span>
                </template>

                <template #action="{ row }">
                    <Badge color="primary" size="sm">{{ row.action }}</Badge>
                </template>

                <template #user="{ row }">
                    <span class="text-content">{{ row.user ?? "Sistema" }}</span>
                </template>

                <template #auditable="{ row }">
                    <span class="text-xs text-content-muted">{{ row.auditable_type }} #{{ row.auditable_id }}</span>
                </template>

                <template #properties="{ row }">
                    <span class="line-clamp-2 text-xs text-content-subtle">{{ summarize(row.properties) }}</span>
                </template>

                <template #empty>
                    <EmptyState :icon="ScrollText" title="Sem Registros" description="Nenhuma ação auditada até agora." />
                </template>
            </DataTable>

            <Pagination :paginator="logs" />
        </Card>
    </div>
</template>
