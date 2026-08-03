<script setup>
import { computed, ref } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import PageHeader from "../../Components/UI/PageHeader.vue";
import Card from "../../Components/UI/Card.vue";
import Button from "../../Components/UI/Button.vue";
import Modal from "../../Components/UI/Modal.vue";
import ConfirmDialog from "../../Components/UI/ConfirmDialog.vue";
import FormField from "../../Components/UI/FormField.vue";
import TextInput from "../../Components/UI/TextInput.vue";
import TextArea from "../../Components/UI/TextArea.vue";
import Toggle from "../../Components/UI/Toggle.vue";
import Badge from "../../Components/UI/Badge.vue";
import { Check, Pencil, ShieldCheck, Trash2, X } from "lucide-vue-next";
import EmptyState from "../../Components/UI/EmptyState.vue";
import { usePermissions } from "../../Composables/usePermissions";

const props = defineProps({
    roles: { type: Array, default: () => [] },
    permission_groups: { type: Array, default: () => [] },
});

const { can } = usePermissions();

const showForm = ref(false);
const editing = ref(null);
const deleting = ref(null);

const allKeys = computed(() =>
    props.permission_groups.flatMap((group) =>
        group.permissions.map((permission) => permission.key),
    ),
);

const form = useForm({
    name: "",
    description: "",
    is_default: false,
    permissions: [],
});

function openForm(role = null) {
    editing.value = role;

    form.defaults({
        name: role?.name ?? "",
        description: role?.description ?? "",
        is_default: role?.is_default ?? false,
        permissions: role?.permissions ?? [],
    });

    form.reset();
    form.clearErrors();
    showForm.value = true;
}

function togglePermission(key) {
    form.permissions = form.permissions.includes(key)
        ? form.permissions.filter((item) => item !== key)
        : [...form.permissions, key];
}

function toggleGroup(group) {
    const keys = group.permissions.map((permission) => permission.key);
    const allSelected = keys.every((key) => form.permissions.includes(key));

    form.permissions = allSelected
        ? form.permissions.filter((key) => !keys.includes(key))
        : [...new Set([...form.permissions, ...keys])];
}

function toggleAll() {
    form.permissions =
        form.permissions.length === allKeys.value.length
            ? []
            : [...allKeys.value];
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (showForm.value = false),
    };

    if (editing.value) {
        form.put(`/papeis/${editing.value.id}`, options);
    } else {
        form.post("/papeis", options);
    }
}

function destroy() {
    router.delete(`/papeis/${deleting.value.id}`, {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
}
</script>

<template>
    <Head title="Papéis e Permissões" />

    <div class="space-y-5 p-4 lg:p-6">
        <PageHeader
            title="Papéis e Permissões"
            subtitle="Cada permissão libera rotas, menus e ações da interface."
        >
            <template #actions>
                <Button
                    v-if="can('roles.create')"
                    :icon="ShieldCheck"
                    @click="openForm()"
                    >Novo Papel</Button
                >
            </template>
        </PageHeader>

        <div
            v-if="roles.length"
            class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
        >
            <Card v-for="role in roles" :key="role.id">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-start gap-2.5">
                        <span
                            class="rounded-control bg-primary-soft p-2 text-primary"
                        >
                            <ShieldCheck :size="18" />
                        </span>

                        <div class="min-w-0 space-y-1">
                            <p
                                class="truncate text-sm font-semibold text-content"
                            >
                                {{ role.name }}
                            </p>
                            <p class="line-clamp-2 text-xs text-content-muted">
                                {{ role.description ?? "Sem descrição." }}
                            </p>
                        </div>
                    </div>

                    <div class="flex shrink-0 gap-1">
                        <button
                            v-if="can('roles.update') && !role.is_locked"
                            type="button"
                            class="rounded-control p-1.5 text-content-subtle transition hover:bg-surface-hover hover:text-content"
                            @click="openForm(role)"
                        >
                            <Pencil :size="16" />
                        </button>
                        <button
                            v-if="can('roles.delete') && !role.is_locked"
                            type="button"
                            class="rounded-control p-1.5 text-content-subtle transition hover:bg-danger-soft hover:text-danger"
                            @click="deleting = role"
                        >
                            <Trash2 :size="16" />
                        </button>
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap gap-1">
                    <Badge color="muted" size="sm"
                        >{{ role.users_count ?? 0 }} Usuários</Badge
                    >
                    <Badge color="primary" size="sm"
                        >{{ role.permissions?.length ?? 0 }} Permissões</Badge
                    >
                    <Badge v-if="role.is_default" color="info" size="sm"
                        >Padrão</Badge
                    >
                    <Badge v-if="role.is_locked" color="warning" size="sm"
                        >Protegido</Badge
                    >
                </div>
            </Card>
        </div>

        <Card v-else>
            <EmptyState
                :icon="ShieldCheck"
                title="Nenhum Papel"
                description="Crie papéis para agrupar permissões."
            />
        </Card>
    </div>

    <Modal
        :open="showForm"
        :title="editing ? 'Editar Papel' : 'Novo Papel'"
        description="Marque tudo o que este papel pode fazer."
        size="lg"
        @close="showForm = false"
    >
        <form id="role-form" class="space-y-5" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <FormField label="Nome" :error="form.errors.name" required>
                    <TextInput
                        v-model="form.name"
                        :invalid="Boolean(form.errors.name)"
                    />
                </FormField>

                <FormField label="Descrição" :error="form.errors.description">
                    <TextArea v-model="form.description" rows="1" />
                </FormField>
            </div>

            <Toggle
                v-model="form.is_default"
                label="Papel padrão"
                description="Aplicado automaticamente a novos usuários."
            />

            <div
                class="flex items-center justify-between border-t border-border pt-4"
            >
                <p class="text-xs font-medium text-content-muted">
                    {{ form.permissions.length }} de
                    {{ allKeys.length }} permissões
                </p>
                <Button
                    size="sm"
                    variant="ghost"
                    type="button"
                    @click="toggleAll"
                    >Marcar Tudo</Button
                >
            </div>

            <div class="space-y-4">
                <div
                    v-for="group in permission_groups"
                    :key="group.group"
                    class="space-y-2"
                >
                    <button
                        type="button"
                        class="flex w-full items-center justify-between text-left"
                        @click="toggleGroup(group)"
                    >
                        <span
                            class="text-xs font-semibold uppercase tracking-wider text-content-subtle"
                        >
                            {{ group.group }}
                        </span>
                        <Check :size="14" class="text-content-subtle" />
                    </button>

                    <div class="grid gap-1.5 sm:grid-cols-2">
                        <button
                            v-for="permission in group.permissions"
                            :key="permission.key"
                            type="button"
                            class="flex items-center gap-2 rounded-control border px-2.5 py-1.5 text-left transition"
                            :class="
                                form.permissions.includes(permission.key)
                                    ? 'border-primary bg-primary-soft'
                                    : 'border-border hover:bg-surface-hover'
                            "
                            @click="togglePermission(permission.key)"
                        >
                            <component
                                :is="
                                    form.permissions.includes(permission.key)
                                        ? Check
                                        : X
                                "
                                :size="13"
                                :class="
                                    form.permissions.includes(permission.key)
                                        ? 'text-primary'
                                        : 'text-content-subtle'
                                "
                            />
                            <span class="min-w-0">
                                <span
                                    class="block truncate text-xs text-content"
                                    >{{ permission.label }}</span
                                >
                                <span
                                    class="block truncate font-mono text-[10px] text-content-subtle"
                                    >{{ permission.key }}</span
                                >
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </form>

        <template #footer>
            <Button variant="secondary" @click="showForm = false"
                >Cancelar</Button
            >
            <Button type="submit" form="role-form" :loading="form.processing"
                >Salvar</Button
            >
        </template>
    </Modal>

    <ConfirmDialog
        :open="deleting !== null"
        title="Excluir Papel"
        :message="`O papel ${deleting?.name} será removido dos usuários vinculados.`"
        confirm-label="Excluir"
        @close="deleting = null"
        @confirm="destroy"
    />
</template>
