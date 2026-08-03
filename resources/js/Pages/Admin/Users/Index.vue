<script setup>
import { computed, ref, watch } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import { Pencil, Trash2, UserPlus } from "lucide-vue-next";
import PageHeader from "../../../Components/UI/PageHeader.vue";
import Card from "../../../Components/UI/Card.vue";
import Button from "../../../Components/UI/Button.vue";
import DataTable from "../../../Components/UI/DataTable.vue";
import Pagination from "../../../Components/UI/Pagination.vue";
import SearchInput from "../../../Components/UI/SearchInput.vue";
import SelectInput from "../../../Components/UI/SelectInput.vue";
import Modal from "../../../Components/UI/Modal.vue";
import ConfirmDialog from "../../../Components/UI/ConfirmDialog.vue";
import FormField from "../../../Components/UI/FormField.vue";
import TextInput from "../../../Components/UI/TextInput.vue";
import Toggle from "../../../Components/UI/Toggle.vue";
import Badge from "../../../Components/UI/Badge.vue";
import EmptyState from "../../../Components/UI/EmptyState.vue";
import { formatRelative } from "../../../Utils/format";

const props = defineProps({
    users: { type: Object, required: true },
    tenants: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const search = ref(props.filters.search ?? "");
const tenantFilter = ref(props.filters.tenant ?? null);
const showForm = ref(false);
const editing = ref(null);
const deleting = ref(null);

const columns = [
    { key: "name", label: "Usuário" },
    { key: "tenants", label: "Tenants" },
    { key: "status", label: "Situação" },
    { key: "actions", label: "", align: "right" },
];

const tenantOptions = computed(() =>
    props.tenants.map((tenant) => ({ value: tenant.id, label: tenant.name })),
);

const form = useForm({
    name: "",
    email: "",
    password: "",
    tenant_ids: [],
    is_super_admin: false,
    is_active: true,
});

function toggleTenant(id) {
    form.tenant_ids = form.tenant_ids.includes(id)
        ? form.tenant_ids.filter((item) => item !== id)
        : [...form.tenant_ids, id];
}

let searchTimeout = null;

function applyFilters() {
    router.get(
        "/admin/usuarios",
        {
            search: search.value || undefined,
            tenant: tenantFilter.value || undefined,
        },
        { preserveState: true, replace: true, only: ["users", "filters"] },
    );
}

watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 350);
});

watch(tenantFilter, applyFilters);

function openForm(user = null) {
    editing.value = user;

    form.defaults({
        name: user?.name ?? "",
        email: user?.email ?? "",
        password: "",
        tenant_ids: user?.tenant_ids ?? [],
        is_super_admin: user?.is_super_admin ?? false,
        is_active: user?.is_active ?? true,
    });

    form.reset();
    form.clearErrors();
    showForm.value = true;
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (showForm.value = false),
    };

    if (editing.value) {
        form.put(`/admin/usuarios/${editing.value.id}`, options);
    } else {
        form.post("/admin/usuarios", options);
    }
}

function destroy() {
    router.delete(`/admin/usuarios/${deleting.value.id}`, {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
}
</script>

<template>
    <Head title="Usuários da Plataforma" />

    <div class="space-y-5 p-4 lg:p-6">
        <PageHeader
            title="Usuários da Plataforma"
            subtitle="Quem acessa a plataforma e a qual tenant pertence."
        >
            <template #actions>
                <SearchInput
                    v-model="search"
                    placeholder="Nome ou email"
                    class="w-52"
                />
                <SelectInput
                    v-model="tenantFilter"
                    :options="tenantOptions"
                    placeholder="Todos os tenants"
                    class="w-48"
                />
                <Button :icon="UserPlus" @click="openForm()"
                    >Novo Usuário</Button
                >
            </template>
        </PageHeader>

        <Card :padded="false">
            <DataTable :columns="columns" :rows="users.data">
                <template #name="{ row }">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-content">
                            {{ row.name }}
                        </p>
                        <p class="truncate text-xs text-content-subtle">
                            {{ row.email }}
                        </p>
                    </div>
                </template>

                <template #tenants="{ row }">
                    <span class="flex flex-wrap gap-1">
                        <Badge
                            v-for="name in row.tenants"
                            :key="name"
                            color="primary"
                            size="sm"
                            >{{ name }}</Badge
                        >
                        <Badge
                            v-if="!row.tenants.length"
                            color="muted"
                            size="sm"
                            >Sem tenant</Badge
                        >
                    </span>
                </template>

                <template #status="{ row }">
                    <div class="flex flex-wrap items-center gap-1">
                        <Badge
                            :color="row.is_active ? 'success' : 'muted'"
                            size="sm"
                        >
                            {{ row.is_active ? "Ativo" : "Inativo" }}
                        </Badge>
                        <Badge
                            v-if="row.is_super_admin"
                            color="warning"
                            size="sm"
                            >Super Admin</Badge
                        >
                        <span class="text-[11px] text-content-subtle">
                            {{
                                row.last_seen_at
                                    ? `Visto ${formatRelative(row.last_seen_at)}`
                                    : "Nunca Acessou"
                            }}
                        </span>
                    </div>
                </template>

                <template #actions="{ row }">
                    <span class="flex justify-end gap-1">
                        <button
                            type="button"
                            class="rounded-control p-1.5 text-content-subtle transition hover:bg-surface-hover hover:text-content"
                            @click="openForm(row)"
                        >
                            <Pencil :size="16" />
                        </button>
                        <button
                            type="button"
                            class="rounded-control p-1.5 text-content-subtle transition hover:bg-danger-soft hover:text-danger"
                            @click="deleting = row"
                        >
                            <Trash2 :size="16" />
                        </button>
                    </span>
                </template>

                <template #empty>
                    <EmptyState
                        :icon="UserPlus"
                        title="Nenhum Usuário"
                        description="Nenhum usuário encontrado com esses filtros."
                    />
                </template>
            </DataTable>

            <Pagination :paginator="users" />
        </Card>
    </div>

    <Modal
        :open="showForm"
        :title="editing ? 'Editar Usuário' : 'Novo Usuário'"
        description="Papéis e filas continuam sendo definidos dentro do tenant."
        @close="showForm = false"
    >
        <form id="admin-user-form" class="space-y-4" @submit.prevent="submit">
            <FormField label="Nome" :error="form.errors.name" required>
                <TextInput
                    v-model="form.name"
                    :invalid="Boolean(form.errors.name)"
                />
            </FormField>

            <div class="grid gap-4 sm:grid-cols-2">
                <FormField label="Email" :error="form.errors.email" required>
                    <TextInput v-model="form.email" type="email" />
                </FormField>

                <FormField
                    label="Senha"
                    :error="form.errors.password"
                    :hint="
                        Editing
                            ? 'Deixe em branco para manter a senha atual.'
                            : Null
                    "
                    :required="!editing"
                >
                    <TextInput
                        v-model="form.password"
                        type="password"
                        placeholder="••••••••"
                    />
                </FormField>
            </div>

            <FormField
                group
                label="Tenants"
                :error="form.errors.tenant_ids"
                hint="Remover um tenant apaga os papéis e filas do usuário nele."
            >
                <div
                    class="flex max-h-40 flex-wrap gap-1.5 overflow-y-auto scrollbar-thin"
                >
                    <button
                        v-for="tenant in tenants"
                        :key="tenant.id"
                        type="button"
                        class="rounded-full border px-2.5 py-1 text-xs transition"
                        :class="
                            form.tenant_ids.includes(tenant.id)
                                ? 'border-primary bg-primary-soft text-primary'
                                : 'border-border text-content-muted hover:bg-surface-hover'
                        "
                        @click="toggleTenant(tenant.id)"
                    >
                        {{ tenant.name }}
                    </button>
                </div>
            </FormField>

            <Toggle v-model="form.is_active" label="Usuário ativo" />
            <Toggle
                v-model="form.is_super_admin"
                label="Administrador da plataforma"
                description="Acessa o painel administrativo e o workspace de qualquer tenant."
            />
        </form>

        <template #footer>
            <Button variant="secondary" @click="showForm = false"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="admin-user-form"
                :loading="form.processing"
                >Salvar</Button
            >
        </template>
    </Modal>

    <ConfirmDialog
        :open="deleting !== null"
        title="Excluir Usuário"
        :message="`O acesso de ${deleting?.name} será removido.`"
        confirm-label="Excluir"
        @close="deleting = null"
        @confirm="destroy"
    />
</template>
