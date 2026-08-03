<script setup>
import { computed, ref } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import PageHeader from "../../Components/UI/PageHeader.vue";
import Card from "../../Components/UI/Card.vue";
import Button from "../../Components/UI/Button.vue";
import DataTable from "../../Components/UI/DataTable.vue";
import Modal from "../../Components/UI/Modal.vue";
import ConfirmDialog from "../../Components/UI/ConfirmDialog.vue";
import FormField from "../../Components/UI/FormField.vue";
import TextInput from "../../Components/UI/TextInput.vue";
import TextArea from "../../Components/UI/TextArea.vue";
import SelectInput from "../../Components/UI/SelectInput.vue";
import Toggle from "../../Components/UI/Toggle.vue";
import Badge from "../../Components/UI/Badge.vue";
import { Pencil, Plus, Tag, Trash2 } from "lucide-vue-next";
import EmptyState from "../../Components/UI/EmptyState.vue";
import { usePermissions } from "../../Composables/usePermissions";

const props = defineProps({
    tags: { type: Array, default: () => [] },
    queues: { type: Array, default: () => [] },
});

const { can } = usePermissions();

const showForm = ref(false);
const editing = ref(null);
const deleting = ref(null);

const colors = ["primary", "success", "warning", "danger", "info", "muted"];

const columns = [
    { key: "name", label: "Tag" },
    { key: "description", label: "Descrição" },
    { key: "automation", label: "Automação" },
    { key: "usage", label: "Uso" },
    { key: "actions", label: "", align: "right" },
];

const queueOptions = computed(() =>
    props.queues.map((queue) => ({ value: queue.id, label: queue.name })),
);

const form = useForm({
    name: "",
    color: "primary",
    icon: "tag",
    description: "",
    automation: { service_queue_id: null, close_conversation: false },
});

function openForm(tag = null) {
    editing.value = tag;

    form.defaults({
        name: tag?.name ?? "",
        color: tag?.color ?? "primary",
        icon: tag?.icon ?? "tag",
        description: tag?.description ?? "",
        automation: {
            service_queue_id: tag?.automation?.service_queue_id ?? null,
            close_conversation: tag?.automation?.close_conversation ?? false,
        },
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
        form.put(`/tags/${editing.value.id}`, options);
    } else {
        form.post("/tags", options);
    }
}

function destroy() {
    router.delete(`/tags/${deleting.value.id}`, {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
}

function queueName(id) {
    return props.queues.find((queue) => queue.id === id)?.name;
}
</script>

<template>
    <Head title="Tags" />

    <div class="space-y-5 p-4 lg:p-6">
        <PageHeader
            title="Tags"
            subtitle="Classifique contatos e atendimentos e dispare automações."
        >
            <template #actions>
                <Button
                    v-if="can('tags.create')"
                    :icon="Plus"
                    @click="openForm()"
                    >Nova Tag</Button
                >
            </template>
        </PageHeader>

        <Card :padded="false">
            <DataTable :columns="columns" :rows="tags">
                <template #name="{ row }">
                    <Badge :color="row.color">
                        <Tag :size="12" />
                        {{ row.name }}
                    </Badge>
                </template>

                <template #description="{ row }">
                    <span class="text-content-muted">{{
                        row.description ?? "—"
                    }}</span>
                </template>

                <template #automation="{ row }">
                    <span class="flex flex-wrap gap-1">
                        <Badge
                            v-if="row.automation?.service_queue_id"
                            color="info"
                            size="sm"
                        >
                            Envia Para
                            {{ queueName(row.automation.service_queue_id) }}
                        </Badge>
                        <Badge
                            v-if="row.automation?.close_conversation"
                            color="warning"
                            size="sm"
                            >Encerra</Badge
                        >
                        <span
                            v-if="
                                !row.automation?.service_queue_id &&
                                !row.automation?.close_conversation
                            "
                            class="text-content-subtle"
                        >
                            —
                        </span>
                    </span>
                </template>

                <template #usage="{ row }">
                    <span class="text-xs text-content-muted">
                        {{ row.contacts_count ?? 0 }} contatos ·
                        {{ row.conversations_count ?? 0 }} atendimentos
                    </span>
                </template>

                <template #actions="{ row }">
                    <span class="flex justify-end gap-1">
                        <button
                            v-if="can('tags.update')"
                            type="button"
                            class="rounded-control p-1.5 text-content-subtle transition hover:bg-surface-hover hover:text-content"
                            @click="openForm(row)"
                        >
                            <Pencil :size="16" />
                        </button>
                        <button
                            v-if="can('tags.delete')"
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
                        :icon="Tag"
                        title="Nenhuma Tag"
                        description="Crie tags para segmentar sua base."
                    />
                </template>
            </DataTable>
        </Card>
    </div>

    <Modal
        :open="showForm"
        :title="editing ? 'Editar Tag' : 'Nova Tag'"
        description="Ao aplicar a tag, as automações abaixo são executadas."
        @close="showForm = false"
    >
        <form id="tag-form" class="space-y-4" @submit.prevent="submit">
            <FormField label="Nome" :error="form.errors.name" required>
                <TextInput
                    v-model="form.name"
                    :invalid="Boolean(form.errors.name)"
                />
            </FormField>

            <FormField group label="Cor">
                <div class="flex gap-1.5">
                    <button
                        v-for="color in colors"
                        :key="color"
                        type="button"
                        class="rounded-full px-2.5 py-1 text-xs capitalize transition"
                        :class="[
                            form.color === color ? 'ring-2 ring-primary' : '',
                            {
                                'bg-primary-soft text-primary':
                                    color === 'primary',
                                'bg-success-soft text-success':
                                    color === 'success',
                                'bg-warning-soft text-warning':
                                    color === 'warning',
                                'bg-danger-soft text-danger':
                                    color === 'danger',
                                'bg-info-soft text-info': color === 'info',
                                'bg-surface-muted text-content-muted':
                                    color === 'muted',
                            },
                        ]"
                        @click="form.color = color"
                    >
                        {{ color }}
                    </button>
                </div>
            </FormField>

            <FormField label="Descrição" :error="form.errors.description">
                <TextArea v-model="form.description" rows="2" />
            </FormField>

            <FormField
                label="Transferir para setor"
                :error="form.errors['automation.service_queue_id']"
            >
                <SelectInput
                    v-model="form.automation.service_queue_id"
                    :options="queueOptions"
                    placeholder="Não transferir"
                />
            </FormField>

            <Toggle
                v-model="form.automation.close_conversation"
                label="Encerrar atendimento"
                description="O atendimento é finalizado assim que a tag é aplicada."
            />
        </form>

        <template #footer>
            <Button variant="secondary" @click="showForm = false"
                >Cancelar</Button
            >
            <Button type="submit" form="tag-form" :loading="form.processing"
                >Salvar</Button
            >
        </template>
    </Modal>

    <ConfirmDialog
        :open="deleting !== null"
        title="Excluir Tag"
        :message="`A tag ${deleting?.name} será removida de todos os contatos e atendimentos.`"
        confirm-label="Excluir"
        @close="deleting = null"
        @confirm="destroy"
    />
</template>
