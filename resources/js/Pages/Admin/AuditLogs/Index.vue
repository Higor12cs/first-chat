<script setup>
import { ScrollText } from "lucide-vue-next";
import { ref, watch } from "vue";
import { Head, router } from "@inertiajs/vue3";
import PageHeader from "../../../Components/UI/PageHeader.vue";
import Card from "../../../Components/UI/Card.vue";
import DataTable from "../../../Components/UI/DataTable.vue";
import Pagination from "../../../Components/UI/Pagination.vue";
import SearchInput from "../../../Components/UI/SearchInput.vue";
import Badge from "../../../Components/UI/Badge.vue";
import EmptyState from "../../../Components/UI/EmptyState.vue";
import { formatDateTime } from "../../../Utils/format";

const props = defineProps({
    logs: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const action = ref(props.filters.action ?? "");

const columns = [
    { key: "created_at", label: "Data" },
    { key: "tenant", label: "Tenant" },
    { key: "action", label: "Ação" },
    { key: "user", label: "Usuário" },
    { key: "properties", label: "Detalhes" },
];

let searchTimeout = null;

watch(action, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get("/admin/auditoria", { action: action.value || undefined }, {
            preserveState: true,
            replace: true,
            only: ["logs", "filters"],
        });
    }, 350);
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
    <Head title="Auditoria Global" />

    <div class="space-y-5 p-4 lg:p-6">
        <PageHeader title="Auditoria Global" subtitle="Ações registradas em todos os tenants da plataforma.">
            <template #actions>
                <SearchInput v-model="action" placeholder="Filtrar por ação" class="w-56" />
            </template>
        </PageHeader>

        <Card :padded="false">
            <DataTable :columns="columns" :rows="logs.data">
                <template #created_at="{ row }">
                    <span class="whitespace-nowrap text-xs text-content-muted">{{ formatDateTime(row.created_at) }}</span>
                </template>

                <template #tenant="{ row }">
                    <Badge color="muted" size="sm">{{ row.tenant ?? "—" }}</Badge>
                </template>

                <template #action="{ row }">
                    <Badge color="primary" size="sm">{{ row.action }}</Badge>
                </template>

                <template #user="{ row }">
                    <span class="text-content">{{ row.user ?? "Sistema" }}</span>
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
