<script setup>
import { computed, ref, watch } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import PageHeader from "../../../Components/UI/PageHeader.vue";
import Card from "../../../Components/UI/Card.vue";
import Button from "../../../Components/UI/Button.vue";
import DataTable from "../../../Components/UI/DataTable.vue";
import Pagination from "../../../Components/UI/Pagination.vue";
import SearchInput from "../../../Components/UI/SearchInput.vue";
import Modal from "../../../Components/UI/Modal.vue";
import ConfirmDialog from "../../../Components/UI/ConfirmDialog.vue";
import FormField from "../../../Components/UI/FormField.vue";
import TextInput from "../../../Components/UI/TextInput.vue";
import SelectInput from "../../../Components/UI/SelectInput.vue";
import Badge from "../../../Components/UI/Badge.vue";
import {
    Building2,
    CalendarClock,
    LogIn,
    Pencil,
    Plug,
    Trash2,
} from "lucide-vue-next";
import EmptyState from "../../../Components/UI/EmptyState.vue";
import { formatCents, formatDateTime } from "../../../Utils/format";

const props = defineProps({
    tenants: { type: Object, required: true },
    filters: { type: Object, default: () => ({}) },
});

const search = ref(props.filters.search ?? "");
const showCreate = ref(false);
const editing = ref(null);
const deleting = ref(null);
const selected = ref([]);

const statusColors = {
    active: "success",
    trialing: "info",
    suspended: "danger",
};
const statusLabels = {
    active: "Ativo",
    trialing: "Em teste",
    suspended: "Suspenso",
};

const statusOptions = [
    { value: "active", label: "Ativo" },
    { value: "trialing", label: "Em Teste" },
    { value: "suspended", label: "Suspenso" },
];

const columns = [
    { key: "select", label: "" },
    { key: "name", label: "Tenant" },
    { key: "connections", label: "Conexões" },
    { key: "usage", label: "Uso" },
    { key: "access", label: "Acesso até" },
    { key: "status", label: "Situação" },
    { key: "actions", label: "", align: "right" },
];

const accessForm = useForm({
    tenants: [],
    access_expires_at: "",
});

const allSelected = computed(
    () =>
        props.tenants.data.length > 0 &&
        selected.value.length === props.tenants.data.length,
);

function toggleAll() {
    selected.value = allSelected.value
        ? []
        : props.tenants.data.map((tenant) => tenant.id);
}

function toggleRow(id) {
    selected.value = selected.value.includes(id)
        ? selected.value.filter((item) => item !== id)
        : [...selected.value, id];
}

function formatAccessDate(value) {
    return value ? value.split("-").reverse().join("/") : "Sem Limite";
}

function applyAccessDate() {
    accessForm
        .transform((data) => ({
            tenants: selected.value,
            access_expires_at: data.access_expires_at || null,
        }))
        .put("/admin/tenants/acesso", {
            preserveScroll: true,
            onSuccess: () => {
                selected.value = [];
                accessForm.reset();
            },
        });
}

const createForm = useForm({
    name: "",
    document: "",
    max_connections: 1,
    owner_name: "",
    owner_email: "",
    owner_password: "",
});

const editForm = useForm({
    name: "",
    document: "",
    status: "active",
    trial_ends_at: null,
    access_expires_at: null,
    price_cents: null,
    max_users: null,
    max_connections: 1,
    max_monthly_messages: null,
    max_monthly_ai_cost_cents: null,
});

const editPriceReais = computed({
    get: () =>
        editForm.price_cents === null ? "" : editForm.price_cents / 100,
    set: (value) => {
        editForm.price_cents =
            value === "" || value === null
                ? null
                : Math.round(Number(value) * 100);
    },
});

const editAiLimitDollars = computed({
    get: () =>
        editForm.max_monthly_ai_cost_cents === null
            ? ""
            : editForm.max_monthly_ai_cost_cents / 100,
    set: (value) => {
        editForm.max_monthly_ai_cost_cents =
            value === "" || value === null
                ? null
                : Math.round(Number(value) * 100);
    },
});

const reducingConnections = computed(
    () =>
        editing.value !== null &&
        Number(editForm.max_connections) < editing.value.connections_count,
);

let searchTimeout = null;

watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(
            "/admin/tenants",
            { search: search.value || undefined },
            {
                preserveState: true,
                replace: true,
                only: ["tenants", "filters"],
            },
        );
    }, 350);
});

function openCreate() {
    createForm.reset();
    createForm.clearErrors();
    showCreate.value = true;
}

function openEdit(tenant) {
    editing.value = tenant;

    editForm.defaults({
        name: tenant.name,
        document: tenant.document ?? "",
        status: tenant.status,
        trial_ends_at: tenant.trial_ends_at,
        access_expires_at: tenant.access_expires_at,
        price_cents: tenant.price_cents,
        max_users: tenant.max_users,
        max_connections: tenant.max_connections ?? 1,
        max_monthly_messages: tenant.max_monthly_messages,
        max_monthly_ai_cost_cents: tenant.max_monthly_ai_cost_cents,
    });

    editForm.reset();
    editForm.clearErrors();
}

function storeTenant() {
    createForm.post("/admin/tenants", {
        preserveScroll: true,
        onSuccess: () => (showCreate.value = false),
    });
}

function updateTenant() {
    editForm.put(`/admin/tenants/${editing.value.id}`, {
        preserveScroll: true,
        onSuccess: () => (editing.value = null),
    });
}

function enterWorkspace(tenant) {
    router.post(`/admin/tenants/${tenant.id}/acessar`);
}

function destroy() {
    router.delete(`/admin/tenants/${deleting.value.id}`, {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
}
</script>

<template>
    <Head title="Tenants" />

    <div class="space-y-5 p-4 lg:p-6">
        <PageHeader
            title="Tenants"
            subtitle="Empresas atendidas pela plataforma e seus limites."
        >
            <template #actions>
                <SearchInput
                    v-model="search"
                    placeholder="Nome do tenant"
                    class="w-52"
                />
                <Button :icon="Building2" @click="openCreate"
                    >Novo Tenant</Button
                >
            </template>
        </PageHeader>

        <Card v-if="selected.length">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div class="flex flex-wrap items-end gap-4">
                    <FormField
                        label="Data limite de acesso"
                        :error="
                            accessForm.errors.access_expires_at ??
                            accessForm.errors.tenants
                        "
                    >
                        <div class="w-44">
                            <TextInput
                                v-model="accessForm.access_expires_at"
                                type="date"
                            />
                        </div>
                    </FormField>
                </div>

                <div class="flex items-center gap-2 pb-1">
                    <Button variant="secondary" @click="selected = []"
                        >Limpar</Button
                    >
                    <Button
                        :icon="CalendarClock"
                        :loading="accessForm.processing"
                        @click="applyAccessDate"
                        >Aplicar aos Selecionados</Button
                    >
                </div>
            </div>
        </Card>

        <Card :padded="false">
            <DataTable :columns="columns" :rows="tenants.data">
                <template #select-header>
                    <input
                        type="checkbox"
                        class="h-4 w-4 cursor-pointer accent-[var(--primary)]"
                        :checked="allSelected"
                        @change="toggleAll"
                    />
                </template>

                <template #select="{ row }">
                    <input
                        type="checkbox"
                        class="h-4 w-4 cursor-pointer accent-[var(--primary)]"
                        :checked="selected.includes(row.id)"
                        @change="toggleRow(row.id)"
                    />
                </template>

                <template #access="{ row }">
                    <div class="space-y-0.5">
                        <p
                            class="text-sm"
                            :class="
                                row.has_valid_access
                                    ? 'text-content'
                                    : 'text-danger'
                            "
                        >
                            {{ formatAccessDate(row.access_expires_at) }}
                        </p>
                        <p
                            v-if="!row.has_valid_access"
                            class="text-[11px] text-danger"
                        >
                            Acesso expirado
                        </p>
                    </div>
                </template>

                <template #name="{ row }">
                    <div class="min-w-0">
                        <p class="truncate text-sm font-medium text-content">
                            {{ row.name }}
                        </p>
                        <p class="truncate text-xs text-content-subtle">
                            {{ row.slug }}
                        </p>
                    </div>
                </template>

                <template #connections="{ row }">
                    <span
                        class="inline-flex items-center gap-1.5 text-sm text-content"
                    >
                        <Plug :size="14" class="text-content-subtle" />
                        {{ row.connections_count }} /
                        {{ row.max_connections ?? "—" }}
                    </span>
                </template>

                <template #usage="{ row }">
                    <span class="text-xs text-content-muted">
                        {{ row.users_count
                        }}<span v-if="row.max_users">/{{ row.max_users }}</span>
                        usuários · {{ row.conversations_count }} atendimentos ·
                        {{ formatCents(row.price_cents) }} por mês
                    </span>
                </template>

                <template #status="{ row }">
                    <div class="space-y-0.5">
                        <Badge
                            :color="statusColors[row.status] ?? 'muted'"
                            size="sm"
                        >
                            {{ statusLabels[row.status] ?? row.status }}
                        </Badge>
                        <p class="text-[11px] text-content-subtle">
                            Criado em {{ formatDateTime(row.created_at) }}
                        </p>
                    </div>
                </template>

                <template #actions="{ row }">
                    <span class="flex justify-end gap-1">
                        <button
                            type="button"
                            class="rounded-control p-1.5 text-content-subtle transition hover:bg-surface-hover hover:text-primary"
                            title="Acessar"
                            @click="enterWorkspace(row)"
                        >
                            <LogIn :size="16" />
                        </button>
                        <button
                            type="button"
                            class="rounded-control p-1.5 text-content-subtle transition hover:bg-surface-hover hover:text-content"
                            title="Editar"
                            @click="openEdit(row)"
                        >
                            <Pencil :size="16" />
                        </button>
                        <button
                            type="button"
                            class="rounded-control p-1.5 text-content-subtle transition hover:bg-danger-soft hover:text-danger"
                            title="Excluir"
                            @click="deleting = row"
                        >
                            <Trash2 :size="16" />
                        </button>
                    </span>
                </template>

                <template #empty>
                    <EmptyState
                        :icon="Building2"
                        title="Nenhum Tenant"
                        description="Cadastre a primeira empresa."
                    />
                </template>
            </DataTable>

            <Pagination :paginator="tenants" />
        </Card>
    </div>

    <Modal
        :open="showCreate"
        title="Novo Tenant"
        description="A empresa é criada junto com o usuário responsável e as conexões contratadas."
        @close="showCreate = false"
    >
        <form
            id="tenant-create-form"
            class="space-y-4"
            @submit.prevent="storeTenant"
        >
            <FormField
                label="Nome da empresa"
                :error="createForm.errors.name"
                required
            >
                <TextInput
                    v-model="createForm.name"
                    :invalid="Boolean(createForm.errors.name)"
                />
            </FormField>

            <div class="grid gap-4 sm:grid-cols-2">
                <FormField
                    label="Documento"
                    :error="createForm.errors.document"
                >
                    <TextInput v-model="createForm.document" />
                </FormField>

                <FormField
                    label="Conexões de WhatsApp"
                    :error="createForm.errors.max_connections"
                    hint="Uma instância é provisionada para cada conexão."
                    required
                >
                    <TextInput
                        v-model="createForm.max_connections"
                        type="number"
                        min="1"
                    />
                </FormField>
            </div>

            <div class="space-y-4 border-t border-border pt-4">
                <FormField
                    label="Nome do responsável"
                    :error="createForm.errors.owner_name"
                    required
                >
                    <TextInput v-model="createForm.owner_name" />
                </FormField>

                <FormField
                    label="Email do responsável"
                    :error="createForm.errors.owner_email"
                    required
                >
                    <TextInput v-model="createForm.owner_email" type="email" />
                </FormField>

                <FormField
                    label="Senha do responsável"
                    :error="createForm.errors.owner_password"
                    required
                >
                    <TextInput
                        v-model="createForm.owner_password"
                        type="password"
                    />
                </FormField>
            </div>
        </form>

        <template #footer>
            <Button variant="secondary" @click="showCreate = false"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="tenant-create-form"
                :loading="createForm.processing"
                >Criar</Button
            >
        </template>
    </Modal>

    <Modal
        :open="editing !== null"
        title="Editar Tenant"
        @close="editing = null"
    >
        <form
            id="tenant-edit-form"
            class="space-y-6"
            @submit.prevent="updateTenant"
        >
            <section class="space-y-4">
                <h3
                    class="text-xs font-semibold uppercase tracking-wide text-content-subtle"
                >
                    Identificação
                </h3>

                <FormField label="Nome" :error="editForm.errors.name" required>
                    <TextInput v-model="editForm.name" />
                </FormField>

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        label="Documento"
                        :error="editForm.errors.document"
                    >
                        <TextInput v-model="editForm.document" />
                    </FormField>

                    <FormField
                        label="Situação"
                        :error="editForm.errors.status"
                        required
                    >
                        <SelectInput
                            v-model="editForm.status"
                            :options="statusOptions"
                        />
                    </FormField>
                </div>
            </section>

            <section class="space-y-4 border-t border-border pt-5">
                <h3
                    class="text-xs font-semibold uppercase tracking-wide text-content-subtle"
                >
                    Acesso
                </h3>

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        label="Fim do teste"
                        :error="editForm.errors.trial_ends_at"
                    >
                        <TextInput
                            v-model="editForm.trial_ends_at"
                            type="date"
                        />
                    </FormField>

                    <FormField
                        label="Data limite de acesso"
                        :error="editForm.errors.access_expires_at"
                        hint="Depois dela o tenant perde o acesso."
                    >
                        <TextInput
                            v-model="editForm.access_expires_at"
                            type="date"
                        />
                    </FormField>
                </div>
            </section>

            <section class="space-y-4 border-t border-border pt-5">
                <div class="space-y-1">
                    <h3
                        class="text-xs font-semibold uppercase tracking-wide text-content-subtle"
                    >
                        Plano e limites
                    </h3>
                    <p class="text-xs text-content-muted">
                        Deixe em branco para não limitar.
                    </p>
                </div>

                <FormField
                    label="Conexões de WhatsApp"
                    :error="editForm.errors.max_connections"
                    hint="Aumentar provisiona novas instâncias na hora."
                    required
                >
                    <TextInput
                        v-model="editForm.max_connections"
                        type="number"
                        min="1"
                    />
                </FormField>

                <p
                    v-if="reducingConnections"
                    class="rounded-control bg-warning-soft px-3 py-2 text-xs text-warning"
                >
                    O tenant já usa {{ editing.connections_count }} conexões.
                    Reduzir o limite não remove as existentes, apenas impede
                    novas.
                </p>

                <div class="grid gap-4 sm:grid-cols-2">
                    <FormField
                        label="Preço mensal (R$)"
                        :error="editForm.errors.price_cents"
                    >
                        <TextInput
                            v-model="editPriceReais"
                            type="number"
                            placeholder="0"
                        />
                    </FormField>

                    <FormField
                        label="Usuários"
                        :error="editForm.errors.max_users"
                    >
                        <TextInput
                            v-model="editForm.max_users"
                            type="number"
                            placeholder="Ilimitado"
                        />
                    </FormField>

                    <FormField
                        label="Mensagens por mês"
                        :error="editForm.errors.max_monthly_messages"
                    >
                        <TextInput
                            v-model="editForm.max_monthly_messages"
                            type="number"
                            placeholder="Ilimitado"
                        />
                    </FormField>

                    <FormField
                        label="Custo de IA por mês (US$)"
                        :error="editForm.errors.max_monthly_ai_cost_cents"
                    >
                        <TextInput
                            v-model="editAiLimitDollars"
                            type="number"
                            placeholder="Ilimitado"
                        />
                    </FormField>
                </div>
            </section>
        </form>

        <template #footer>
            <Button variant="secondary" @click="editing = null"
                >Cancelar</Button
            >
            <Button
                type="submit"
                form="tenant-edit-form"
                :loading="editForm.processing"
                >Salvar</Button
            >
        </template>
    </Modal>

    <ConfirmDialog
        :open="deleting !== null"
        title="Excluir Tenant"
        :message="`Todos os dados de ${deleting?.name} serão removidos permanentemente.`"
        confirm-label="Excluir"
        @close="deleting = null"
        @confirm="destroy"
    />
</template>
