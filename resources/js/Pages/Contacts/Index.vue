<script setup>
import { computed, ref, watch } from "vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import PageHeader from "../../Components/UI/PageHeader.vue";
import Card from "../../Components/UI/Card.vue";
import Button from "../../Components/UI/Button.vue";
import DataTable from "../../Components/UI/DataTable.vue";
import Pagination from "../../Components/UI/Pagination.vue";
import SearchInput from "../../Components/UI/SearchInput.vue";
import SelectInput from "../../Components/UI/SelectInput.vue";
import Modal from "../../Components/UI/Modal.vue";
import ConfirmDialog from "../../Components/UI/ConfirmDialog.vue";
import FormField from "../../Components/UI/FormField.vue";
import TextInput from "../../Components/UI/TextInput.vue";
import PhoneInput from "../../Components/UI/PhoneInput.vue";
import TextArea from "../../Components/UI/TextArea.vue";
import Toggle from "../../Components/UI/Toggle.vue";
import EmptyState from "../../Components/UI/EmptyState.vue";
import Avatar from "../../Components/UI/Avatar.vue";
import Badge from "../../Components/UI/Badge.vue";
import { Pencil, Plus, Trash2, Users } from "lucide-vue-next";
import { formatPhone, formatRelative } from "../../Utils/format";
import { usePermissions } from "../../Composables/usePermissions";

const props = defineProps({
    contacts: { type: Object, required: true },
    tags: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const { can } = usePermissions();

const search = ref(props.filters.search ?? "");
const tag = ref(props.filters.tag ?? null);

const editing = ref(null);
const showForm = ref(false);
const deleting = ref(null);

const columns = [
    { key: "name", label: "Contato" },
    { key: "phone", label: "Telefone" },
    { key: "email", label: "Email" },
    { key: "tags", label: "Tags" },
    { key: "last_interaction_at", label: "Última Interação" },
    { key: "actions", label: "", align: "right" },
];

const tagOptions = computed(() =>
    props.tags.map((item) => ({ value: item.id, label: item.name })),
);

const form = useForm({
    name: "",
    nickname: "",
    phone: "",
    email: "",
    document: "",
    notes: "",
    is_blocked: false,
    tags: [],
});

let searchTimeout = null;

function applyFilters() {
    router.get(
        "/contatos",
        { search: search.value || undefined, tag: tag.value || undefined },
        { preserveState: true, replace: true, only: ["contacts", "filters"] },
    );
}

watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 350);
});

watch(tag, applyFilters);

function openForm(contact = null) {
    editing.value = contact;

    form.defaults({
        name: contact?.legal_name ?? contact?.name ?? "",
        nickname: contact?.nickname ?? "",
        phone: contact?.phone ?? "",
        email: contact?.email ?? "",
        document: contact?.document ?? "",
        notes: contact?.notes ?? "",
        is_blocked: contact?.is_blocked ?? false,
        tags: contact?.tags?.map((item) => item.id) ?? [],
    });

    form.reset();
    form.clearErrors();
    showForm.value = true;
}

function toggleTag(id) {
    form.tags = form.tags.includes(id)
        ? form.tags.filter((item) => item !== id)
        : [...form.tags, id];
}

function submit() {
    const options = {
        preserveScroll: true,
        onSuccess: () => (showForm.value = false),
    };

    if (editing.value) {
        form.put(`/contatos/${editing.value.id}`, options);
    } else {
        form.post("/contatos", options);
    }
}

function destroy() {
    router.delete(`/contatos/${deleting.value.id}`, {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
}
</script>

<template>
    <Head title="Contatos" />

    <div class="space-y-5 p-4 lg:p-6">
        <PageHeader
            title="Contatos"
            subtitle="Base de pessoas que já interagiram com a sua operação."
        >
            <template #actions>
                <Button
                    v-if="can('contacts.create')"
                    :icon="Plus"
                    @click="openForm()"
                    >Novo Contato</Button
                >
            </template>
        </PageHeader>

        <Card :padded="false">
            <template #actions>
                <div class="flex flex-wrap items-center gap-2">
                    <SearchInput
                        v-model="search"
                        placeholder="Nome, telefone ou email"
                        class="w-56"
                    />
                    <SelectInput
                        v-model="tag"
                        :options="tagOptions"
                        placeholder="Todas as tags"
                    />
                </div>
            </template>

            <DataTable :columns="columns" :rows="contacts.data">
                <template #name="{ row }">
                    <Link
                        :href="`/contatos/${row.id}`"
                        class="flex items-center gap-2.5"
                    >
                        <Avatar
                            :name="row.name"
                            :src="row.avatar_url"
                            :size="32"
                        />
                        <span class="space-y-0.5">
                            <span
                                class="block text-sm font-medium text-content"
                                >{{ row.name }}</span
                            >
                            <span class="block text-xs text-content-subtle">
                                <template v-if="row.nickname"
                                    >{{ row.legal_name }} ·
                                </template>
                                {{ row.conversations_count ?? 0 }} atendimentos
                            </span>
                        </span>
                    </Link>
                </template>

                <template #phone="{ row }">
                    <span class="text-content-muted">{{
                        formatPhone(row.phone) || "—"
                    }}</span>
                </template>

                <template #email="{ row }">
                    <span class="text-content-muted">{{
                        row.email ?? "—"
                    }}</span>
                </template>

                <template #tags="{ row }">
                    <span class="flex flex-wrap gap-1">
                        <Badge
                            v-for="item in row.tags"
                            :key="item.id"
                            :color="item.color"
                            size="sm"
                        >
                            {{ item.name }}
                        </Badge>
                        <Badge v-if="row.is_blocked" color="danger" size="sm"
                            >Bloqueado</Badge
                        >
                    </span>
                </template>

                <template #last_interaction_at="{ row }">
                    <span class="text-content-muted">{{
                        formatRelative(row.last_interaction_at)
                    }}</span>
                </template>

                <template #actions="{ row }">
                    <span class="flex justify-end gap-1">
                        <button
                            v-if="can('contacts.update')"
                            type="button"
                            class="rounded-control p-1.5 text-content-subtle transition hover:bg-surface-hover hover:text-content"
                            @click="openForm(row)"
                        >
                            <Pencil :size="16" />
                        </button>
                        <button
                            v-if="can('contacts.delete')"
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
                        title="Nenhum Contato"
                        description="Os contatos são criados automaticamente quando alguém envia mensagem."
                    />
                </template>
            </DataTable>

            <Pagination :paginator="contacts" />
        </Card>
    </div>

    <Modal
        :open="showForm"
        :title="editing ? 'Editar Contato' : 'Novo Contato'"
        description="Dados cadastrais usados em todos os canais."
        @close="showForm = false"
    >
        <form id="contact-form" class="space-y-4" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <FormField label="Nome" :error="form.errors.name" required>
                    <TextInput
                        v-model="form.name"
                        :invalid="Boolean(form.errors.name)"
                    />
                </FormField>

                <FormField
                    label="Apelido"
                    :error="form.errors.nickname"
                    hint="Substitui o nome vindo do WhatsApp."
                >
                    <TextInput v-model="form.nickname" />
                </FormField>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <FormField label="Telefone" :error="form.errors.phone">
                    <PhoneInput
                        v-model="form.phone"
                        with-country
                        placeholder="+55 (11) 98888-7777"
                        :invalid="Boolean(form.errors.phone)"
                    />
                </FormField>

                <FormField label="Email" :error="form.errors.email">
                    <TextInput v-model="form.email" type="email" />
                </FormField>
            </div>

            <FormField label="Documento" :error="form.errors.document">
                <TextInput v-model="form.document" />
            </FormField>

            <FormField label="Observações" :error="form.errors.notes">
                <TextArea v-model="form.notes" rows="3" />
            </FormField>

            <FormField group label="Tags">
                <div class="flex flex-wrap gap-1.5">
                    <button
                        v-for="item in tags"
                        :key="item.id"
                        type="button"
                        class="rounded-full border px-2 py-0.5 text-xs transition"
                        :class="
                            form.tags.includes(item.id)
                                ? 'border-primary bg-primary-soft text-primary'
                                : 'border-border text-content-muted hover:bg-surface-hover'
                        "
                        @click="toggleTag(item.id)"
                    >
                        {{ item.name }}
                    </button>
                </div>
            </FormField>

            <Toggle
                v-model="form.is_blocked"
                label="Bloquear contato"
                description="Mensagens recebidas deste contato não abrem novos atendimentos."
            />
        </form>

        <template #footer>
            <Button variant="secondary" @click="showForm = false"
                >Cancelar</Button
            >
            <Button type="submit" form="contact-form" :loading="form.processing"
                >Salvar</Button
            >
        </template>
    </Modal>

    <ConfirmDialog
        :open="deleting !== null"
        title="Excluir Contato"
        :message="`O contato ${deleting?.name} e todo o histórico serão removidos.`"
        confirm-label="Excluir"
        @close="deleting = null"
        @confirm="destroy"
    />
</template>
