<script setup>
import { ref } from "vue";
import { Head, Link, router } from "@inertiajs/vue3";
import PageHeader from "../../Components/UI/PageHeader.vue";
import Card from "../../Components/UI/Card.vue";
import Button from "../../Components/UI/Button.vue";
import Badge from "../../Components/UI/Badge.vue";
import { Pencil, Plus, Sparkles, Trash2 } from "lucide-vue-next";
import EmptyState from "../../Components/UI/EmptyState.vue";
import ConfirmDialog from "../../Components/UI/ConfirmDialog.vue";
import { formatUsdCents } from "../../Utils/format";
import { usePermissions } from "../../Composables/usePermissions";

defineProps({
    objectives: { type: Array, default: () => [] },
});

const { can } = usePermissions();
const deleting = ref(null);

function destroy() {
    router.delete(`/objetivos-de-ia/${deleting.value.id}`, {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
}

function budgetPercent(objective) {
    if (!objective.cost_limit_cents) {
        return 0;
    }

    return Math.min(
        100,
        Math.round(
            ((objective.spent_cents ?? 0) / objective.cost_limit_cents) * 100,
        ),
    );
}
</script>

<template>
    <Head title="Objetivos de IA" />

    <div class="space-y-5 p-4 lg:p-6">
        <PageHeader
            title="Objetivos de IA"
            subtitle="Cada objetivo define o comportamento da IA: prompt, modelo, ferramentas e limites."
        >
            <template #actions>
                <Button
                    v-if="can('ai-objectives.create')"
                    :icon="Plus"
                    href="/objetivos-de-ia/novo"
                    >Novo Objetivo</Button
                >
            </template>
        </PageHeader>

        <div
            v-if="objectives.length"
            class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
        >
            <Card v-for="objective in objectives" :key="objective.id">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-start gap-2.5">
                        <span
                            class="rounded-control bg-primary-soft p-2 text-primary"
                        >
                            <Sparkles :size="18" />
                        </span>

                        <div class="min-w-0 space-y-1">
                            <p
                                class="truncate text-sm font-semibold text-content"
                            >
                                {{ objective.name }}
                            </p>
                            <p class="line-clamp-2 text-xs text-content-muted">
                                {{ objective.description ?? "Sem descrição." }}
                            </p>
                        </div>
                    </div>

                    <div class="flex shrink-0 gap-1">
                        <Link
                            v-if="can('ai-objectives.update')"
                            :href="`/objetivos-de-ia/${objective.id}/editar`"
                            class="rounded-control p-1.5 text-content-subtle transition hover:bg-surface-hover hover:text-content"
                        >
                            <Pencil :size="16" />
                        </Link>
                        <button
                            v-if="can('ai-objectives.delete')"
                            type="button"
                            class="rounded-control p-1.5 text-content-subtle transition hover:bg-danger-soft hover:text-danger"
                            @click="deleting = objective"
                        >
                            <Trash2 :size="16" />
                        </button>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap gap-1">
                    <Badge
                        :color="objective.is_active ? 'success' : 'muted'"
                        size="sm"
                    >
                        {{ objective.is_active ? "Ativo" : "Inativo" }}
                    </Badge>
                    <Badge color="primary" size="sm">{{
                        objective.provider
                    }}</Badge>
                    <Badge color="muted" size="sm">{{ objective.model }}</Badge>
                    <Badge color="info" size="sm"
                        >{{ objective.tools?.length ?? 0 }} Ferramentas</Badge
                    >
                </div>

                <div v-if="objective.cost_limit_cents" class="mt-3 space-y-1">
                    <div
                        class="flex justify-between text-[11px] text-content-subtle"
                    >
                        <span>Consumo</span>
                        <span
                            >{{ formatUsdCents(objective.spent_cents ?? 0) }} de
                            {{ formatUsdCents(objective.cost_limit_cents) }}</span
                        >
                    </div>
                    <div
                        class="h-1.5 overflow-hidden rounded-full bg-surface-muted"
                    >
                        <div
                            class="h-full rounded-full transition-all"
                            :class="
                                budgetPercent(objective) >= 90
                                    ? 'bg-danger'
                                    : 'bg-primary'
                            "
                            :style="{ width: `${budgetPercent(objective)}%` }"
                        />
                    </div>
                </div>

                <p v-else class="mt-3 text-[11px] text-content-subtle">
                    Sem limite de custo definido.
                </p>
            </Card>
        </div>

        <Card v-else>
            <EmptyState
                :icon="Sparkles"
                title="Nenhum Objetivo"
                description="Crie um objetivo para que a IA atenda com um propósito claro."
            />
        </Card>
    </div>

    <ConfirmDialog
        :open="deleting !== null"
        title="Excluir Objetivo"
        :message="`O objetivo ${deleting?.name} deixará de atender e o histórico será mantido.`"
        confirm-label="Excluir"
        @close="deleting = null"
        @confirm="destroy"
    />
</template>
