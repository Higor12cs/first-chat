<script setup>
import { computed, ref } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import { Building2, Check, LogOut, ShieldCheck } from "lucide-vue-next";
import Button from "../../Components/UI/Button.vue";
import SearchInput from "../../Components/UI/SearchInput.vue";
import EmptyState from "../../Components/UI/EmptyState.vue";

const props = defineProps({
    tenants: { type: Array, default: () => [] },
    current_tenant_id: { type: String, default: null },
    is_super_admin: { type: Boolean, default: false },
});

const search = ref("");

const form = useForm({ tenant_id: null });

const filtered = computed(() => {
    const term = search.value.trim().toLowerCase();

    if (term === "") {
        return props.tenants;
    }

    return props.tenants.filter(
        (tenant) =>
            tenant.name.toLowerCase().includes(term) ||
            tenant.slug.toLowerCase().includes(term),
    );
});

function choose(tenant) {
    form.tenant_id = tenant.id;
    form.post("/selecionar-conta");
}

function logout() {
    router.post("/sair");
}
</script>

<template>
    <Head title="Selecionar Conta" />

    <div class="space-y-4">
        <div class="space-y-1">
            <h2 class="text-base font-semibold text-content">
                Selecionar Conta
            </h2>
            <p class="text-sm text-content-muted">
                {{
                    is_super_admin
                        ? "Escolha a conta que você quer operar."
                        : "Você atende em mais de uma conta. Escolha por onde começar."
                }}
            </p>
        </div>

        <SearchInput
            v-if="tenants.length > 6"
            v-model="search"
            placeholder="Buscar conta"
        />

        <p v-if="form.errors.tenant_id" class="text-xs text-danger">
            {{ form.errors.tenant_id }}
        </p>

        <div
            v-if="filtered.length"
            class="max-h-80 space-y-1.5 overflow-y-auto scrollbar-thin"
        >
            <button
                v-for="tenant in filtered"
                :key="tenant.id"
                type="button"
                class="flex w-full items-center gap-3 rounded-control border border-border px-3 py-2.5 text-left transition hover:border-primary hover:bg-surface-hover disabled:opacity-60"
                :disabled="form.processing"
                @click="choose(tenant)"
            >
                <span
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-control bg-primary-soft text-primary"
                >
                    <Building2 :size="16" />
                </span>

                <span class="min-w-0 flex-1">
                    <span
                        class="block truncate text-sm font-medium text-content"
                        >{{ tenant.name }}</span
                    >
                    <span class="block truncate text-xs text-content-subtle">{{
                        tenant.slug
                    }}</span>
                </span>

                <Check
                    v-if="tenant.id === current_tenant_id"
                    :size="16"
                    class="shrink-0 text-primary"
                />
            </button>
        </div>

        <EmptyState
            v-else
            :icon="Building2"
            title="Nenhuma Conta Disponível"
            description="Nenhuma conta ativa está liberada para o seu acesso. Fale com o suporte."
        />

        <div class="flex items-center justify-between gap-2 border-t border-border pt-4">
            <Button
                v-if="is_super_admin"
                variant="secondary"
                :icon="ShieldCheck"
                @click="router.get('/admin/tenants')"
                >Administração</Button
            >
            <span v-else />

            <Button variant="secondary" :icon="LogOut" @click="logout"
                >Sair</Button
            >
        </div>
    </div>
</template>
