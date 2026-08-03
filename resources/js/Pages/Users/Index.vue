<script setup>
import { ref, watch } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import PageHeader from "../../Components/UI/PageHeader.vue";
import Card from "../../Components/UI/Card.vue";
import Button from "../../Components/UI/Button.vue";
import DataTable from "../../Components/UI/DataTable.vue";
import Pagination from "../../Components/UI/Pagination.vue";
import SearchInput from "../../Components/UI/SearchInput.vue";
import Modal from "../../Components/UI/Modal.vue";
import ConfirmDialog from "../../Components/UI/ConfirmDialog.vue";
import FormField from "../../Components/UI/FormField.vue";
import TextInput from "../../Components/UI/TextInput.vue";
import PhoneInput from "../../Components/UI/PhoneInput.vue";
import Toggle from "../../Components/UI/Toggle.vue";
import Badge from "../../Components/UI/Badge.vue";
import Avatar from "../../Components/UI/Avatar.vue";
import { Pencil, Trash2, UserPlus, Users } from "lucide-vue-next";
import EmptyState from "../../Components/UI/EmptyState.vue";
import { formatRelative } from "../../Utils/format";
import { usePermissions } from "../../Composables/usePermissions";

const props = defineProps({
    users: { type: Object, required: true },
    roles: { type: Array, default: () => [] },
    queues: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const { can } = usePermissions();

const search = ref(props.filters.search ?? "");
const showForm = ref(false);
const editing = ref(null);
const deleting = ref(null);

const weekdays = [
    { value: 1, label: "Seg" },
    { value: 2, label: "Ter" },
    { value: 3, label: "Qua" },
    { value: 4, label: "Qui" },
    { value: 5, label: "Sex" },
    { value: 6, label: "Sáb" },
    { value: 0, label: "Dom" },
];

const columns = [
    { key: "name", label: "Usuário" },
    { key: "roles", label: "Papéis" },
    { key: "queues", label: "Setores" },
    { key: "availability", label: "Disponibilidade" },
    { key: "actions", label: "", align: "right" },
];

const signatureOptions = [
    { value: true, label: "Sempre Assinar" },
    { value: false, label: "Nunca Assinar" },
];

const form = useForm({
    name: "",
    email: "",
    password: "",
    password_confirmation: "",
    phone: "",
    is_active: true,
    hides_other_conversations: false,
    signs_messages: null,
    work_days: [],
    work_starts_at: "",
    work_ends_at: "",
    auto_lock_minutes: null,
    blocked_until: null,
    roles: [],
    service_queues: [],
});

let searchTimeout = null;

function applyFilters() {
    router.get(
        "/usuarios",
        { search: search.value || undefined },
        {
            preserveState: true,
            replace: true,
            only: ["users", "filters"],
        },
    );
}

watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 350);
});

function openForm(user = null) {
    editing.value = user;

    form.defaults({
        name: user?.name ?? "",
        email: user?.email ?? "",
        password: "",
        password_confirmation: "",
        phone: user?.phone ?? "",
        is_active: user?.is_active ?? true,
        hides_other_conversations: user?.hides_other_conversations ?? false,
        signs_messages: user?.signs_messages ?? null,
        work_days: user?.work_days ?? [],
        work_starts_at: user?.work_starts_at ?? "",
        work_ends_at: user?.work_ends_at ?? "",
        auto_lock_minutes: user?.auto_lock_minutes ?? null,
        blocked_until: user?.blocked_until?.slice(0, 10) ?? null,
        roles: user?.roles?.map((role) => role.id) ?? [],
        service_queues: user?.service_queues?.map((queue) => queue.id) ?? [],
    });

    form.reset();
    form.clearErrors();
    showForm.value = true;
}

function toggleIn(field, value) {
    form[field] = form[field].includes(value)
        ? form[field].filter((item) => item !== value)
        : [...form[field], value];
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (showForm.value = false),
    };

    if (editing.value) {
        form.put(`/usuarios/${editing.value.id}`, options);
    } else {
        form.post("/usuarios", options);
    }
}

function destroy() {
    router.delete(`/usuarios/${deleting.value.id}`, {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
}
</script>

<template>
    <Head title="Usuários" />

    <div class="space-y-5 p-4 lg:p-6">
        <PageHeader
            title="Usuários"
            subtitle="Quem atende, com quais papéis, filas e horários."
        >
            <template #actions>
                <SearchInput
                    v-model="search"
                    placeholder="Nome ou email"
                    class="w-52"
                />
                <Button
                    v-if="can('users.create')"
                    :icon="UserPlus"
                    @click="openForm()"
                    >Novo Usuário</Button
                >
            </template>
        </PageHeader>

        <Card :padded="false">
            <DataTable :columns="columns" :rows="users.data">
                <template #name="{ row }">
                    <div class="flex items-center gap-2.5">
                        <Avatar
                            :name="row.name"
                            :src="row.avatar_url"
                            :size="32"
                        />
                        <div class="min-w-0">
                            <p
                                class="truncate text-sm font-medium text-content"
                            >
                                {{ row.name }}
                            </p>
                            <p class="truncate text-xs text-content-subtle">
                                {{ row.email }}
                            </p>
                        </div>
                    </div>
                </template>

                <template #roles="{ row }">
                    <span class="flex flex-wrap gap-1">
                        <Badge
                            v-for="role in row.roles"
                            :key="role.id"
                            color="primary"
                            size="sm"
                            >{{ role.name }}</Badge
                        >
                        <Badge
                            v-if="row.is_super_admin"
                            color="danger"
                            size="sm"
                            >Super Admin</Badge
                        >
                    </span>
                </template>

                <template #queues="{ row }">
                    <span class="flex flex-wrap gap-1">
                        <Badge
                            v-for="queue in row.service_queues"
                            :key="queue.id"
                            color="muted"
                            size="sm"
                        >
                            {{ queue.name }}
                        </Badge>
                        <span
                            v-if="!row.service_queues?.length"
                            class="text-xs text-content-subtle"
                            >—</span
                        >
                    </span>
                </template>

                <template #availability="{ row }">
                    <div class="space-y-0.5 text-xs">
                        <Badge
                            :color="row.is_active ? 'success' : 'danger'"
                            size="sm"
                        >
                            {{ row.is_active ? "Ativo" : "Inativo" }}
                        </Badge>
                        <p
                            v-if="row.work_starts_at"
                            class="text-content-subtle"
                        >
                            {{ row.work_starts_at }} às {{ row.work_ends_at }}
                        </p>
                        <p v-if="row.blocked_until" class="text-danger">
                            Bloqueado até {{ row.blocked_until.slice(0, 10) }}
                        </p>
                        <p class="text-content-subtle">
                            Visto {{ formatRelative(row.last_seen_at) }}
                        </p>
                    </div>
                </template>

                <template #actions="{ row }">
                    <span class="flex justify-end gap-1">
                        <button
                            v-if="can('users.update')"
                            type="button"
                            class="rounded-control p-1.5 text-content-subtle transition hover:bg-surface-hover hover:text-content"
                            @click="openForm(row)"
                        >
                            <Pencil :size="16" />
                        </button>
                        <button
                            v-if="can('users.delete')"
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
                        :icon="Users"
                        title="Nenhum Usuário"
                        description="Convide a sua equipe para atender."
                    />
                </template>
            </DataTable>

            <Pagination :paginator="users" />
        </Card>
    </div>

    <Modal
        :open="showForm"
        :title="editing ? 'Editar Usuário' : 'Novo Usuário'"
        description="Acesso, permissões, filas e janela de atendimento."
        size="lg"
        @close="showForm = false"
    >
        <form id="user-form" class="space-y-5" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <FormField label="Nome" :error="form.errors.name" required>
                    <TextInput
                        v-model="form.name"
                        :invalid="Boolean(form.errors.name)"
                    />
                </FormField>

                <FormField label="Email" :error="form.errors.email" required>
                    <TextInput
                        v-model="form.email"
                        type="email"
                        :invalid="Boolean(form.errors.email)"
                    />
                </FormField>

                <FormField label="Telefone" :error="form.errors.phone">
                    <PhoneInput
                        v-model="form.phone"
                        placeholder="(11) 98888-7777"
                        :invalid="Boolean(form.errors.phone)"
                    />
                </FormField>

                <FormField
                    label="Bloqueio temporário"
                    :error="form.errors.blocked_until"
                    hint="O acesso fica suspenso até a data."
                >
                    <TextInput v-model="form.blocked_until" type="date" />
                </FormField>

                <FormField
                    label="Senha"
                    :error="form.errors.password"
                    :required="!editing"
                    :hint="
                        Editing
                            ? 'Deixe em branco para manter a senha atual.'
                            : Null
                    "
                >
                    <TextInput
                        v-model="form.password"
                        type="password"
                        :invalid="Boolean(form.errors.password)"
                    />
                </FormField>

                <FormField label="Confirmar senha">
                    <TextInput
                        v-model="form.password_confirmation"
                        type="password"
                    />
                </FormField>
            </div>

            <FormField group label="Papéis" :error="form.errors.roles">
                <div class="flex flex-wrap gap-1.5">
                    <button
                        v-for="role in roles"
                        :key="role.id"
                        type="button"
                        class="rounded-full border px-2.5 py-1 text-xs transition"
                        :class="
                            form.roles.includes(role.id)
                                ? 'border-primary bg-primary-soft text-primary'
                                : 'border-border text-content-muted hover:bg-surface-hover'
                        "
                        @click="toggleIn('roles', role.id)"
                    >
                        {{ role.name }}
                    </button>
                </div>
            </FormField>

            <FormField
                group
                label="Setores"
                :error="form.errors.service_queues"
            >
                <div class="flex flex-wrap gap-1.5">
                    <button
                        v-for="queue in queues"
                        :key="queue.id"
                        type="button"
                        class="rounded-full border px-2.5 py-1 text-xs transition"
                        :class="
                            form.service_queues.includes(queue.id)
                                ? 'border-primary bg-primary-soft text-primary'
                                : 'border-border text-content-muted hover:bg-surface-hover'
                        "
                        @click="toggleIn('service_queues', queue.id)"
                    >
                        {{ queue.name }}
                    </button>
                </div>
            </FormField>

            <FormField
                group
                label="Dias de trabalho"
                :error="form.errors.work_days"
            >
                <div class="flex flex-wrap gap-1.5">
                    <button
                        v-for="day in weekdays"
                        :key="day.value"
                        type="button"
                        class="w-12 rounded-control border py-1 text-xs transition"
                        :class="
                            form.work_days.includes(day.value)
                                ? 'border-primary bg-primary-soft text-primary'
                                : 'border-border text-content-muted hover:bg-surface-hover'
                        "
                        @click="toggleIn('work_days', day.value)"
                    >
                        {{ day.label }}
                    </button>
                </div>
            </FormField>

            <div class="grid gap-4 sm:grid-cols-3">
                <FormField label="Início" :error="form.errors.work_starts_at">
                    <TextInput v-model="form.work_starts_at" type="time" />
                </FormField>

                <FormField label="Fim" :error="form.errors.work_ends_at">
                    <TextInput v-model="form.work_ends_at" type="time" />
                </FormField>

                <FormField
                    label="Bloqueio automático (min)"
                    :error="form.errors.auto_lock_minutes"
                >
                    <TextInput
                        v-model="form.auto_lock_minutes"
                        type="number"
                        placeholder="Desativado"
                    />
                </FormField>
            </div>

            <div class="space-y-3">
                <Toggle v-model="form.is_active" label="Usuário ativo" />
                <Toggle
                    v-model="form.hides_other_conversations"
                    label="Ocultar atendimentos de outros"
                    description="O usuário vê apenas os próprios atendimentos e os sem responsável."
                />

                <FormField
                    label="Assinatura nas mensagens"
                    :error="form.errors.signs_messages"
                >
                    <SelectInput
                        v-model="form.signs_messages"
                        :options="signatureOptions"
                        placeholder="Seguir a conta"
                    />
                </FormField>
            </div>
        </form>

        <template #footer>
            <Button variant="secondary" @click="showForm = false"
                >Cancelar</Button
            >
            <Button type="submit" form="user-form" :loading="form.processing"
                >Salvar</Button
            >
        </template>
    </Modal>

    <ConfirmDialog
        :open="deleting !== null"
        title="Remover Usuário"
        :message="`O usuário ${deleting?.name} perde o acesso a esta conta imediatamente.`"
        confirm-label="Remover"
        @close="deleting = null"
        @confirm="destroy"
    />
</template>
