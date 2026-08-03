<script setup>
import { ref } from "vue";
import { Head, Link, router, useForm } from "@inertiajs/vue3";
import PageHeader from "../../Components/UI/PageHeader.vue";
import Card from "../../Components/UI/Card.vue";
import Button from "../../Components/UI/Button.vue";
import Modal from "../../Components/UI/Modal.vue";
import ConfirmDialog from "../../Components/UI/ConfirmDialog.vue";
import FormField from "../../Components/UI/FormField.vue";
import TextInput from "../../Components/UI/TextInput.vue";
import TextArea from "../../Components/UI/TextArea.vue";
import Badge from "../../Components/UI/Badge.vue";
import { Pencil, Trash2, Workflow } from "lucide-vue-next";
import EmptyState from "../../Components/UI/EmptyState.vue";
import { formatDateTime } from "../../Utils/format";
import { usePermissions } from "../../Composables/usePermissions";

defineProps({
    flows: { type: Array, default: () => [] },
});

const { can } = usePermissions();

const showForm = ref(false);
const deleting = ref(null);

const form = useForm({ name: "", description: "" });

function openForm() {
    form.reset();
    form.clearErrors();
    showForm.value = true;
}

function submit() {
    form.post("/fluxos", { onSuccess: () => (showForm.value = false) });
}

function destroy() {
    router.delete(`/fluxos/${deleting.value.id}`, {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
}
</script>

<template>
    <Head title="Fluxos" />

    <div class="space-y-5 p-4 lg:p-6">
        <PageHeader
            title="Fluxos"
            subtitle="Chatbots montados visualmente, sem depender de código."
        >
            <template #actions>
                <Button
                    v-if="can('chat-flows.create')"
                    :icon="Workflow"
                    @click="openForm"
                    >Novo Fluxo</Button
                >
            </template>
        </PageHeader>

        <div
            v-if="flows.length"
            class="grid gap-4 md:grid-cols-2 xl:grid-cols-3"
        >
            <Card v-for="flow in flows" :key="flow.id">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-start gap-2.5">
                        <span
                            class="rounded-control bg-primary-soft p-2 text-primary"
                        >
                            <Workflow :size="18" />
                        </span>

                        <div class="min-w-0 space-y-1">
                            <Link
                                :href="`/fluxos/${flow.id}`"
                                class="block truncate text-sm font-semibold text-content hover:underline"
                            >
                                {{ flow.name }}
                            </Link>
                            <p class="line-clamp-2 text-xs text-content-muted">
                                {{ flow.description ?? "Sem descrição." }}
                            </p>
                        </div>
                    </div>

                    <button
                        v-if="can('chat-flows.delete')"
                        type="button"
                        class="shrink-0 rounded-control p-1.5 text-content-subtle transition hover:bg-danger-soft hover:text-danger"
                        @click="deleting = flow"
                    >
                        <Trash2 :size="16" />
                    </button>
                </div>

                <div class="mt-3 flex flex-wrap gap-1">
                    <Badge
                        :color="flow.is_active ? 'success' : 'muted'"
                        size="sm"
                    >
                        {{ flow.is_active ? "Ativo" : "Inativo" }}
                    </Badge>
                    <Badge color="muted" size="sm"
                        >{{ flow.nodes?.length ?? 0 }} Blocos</Badge
                    >
                    <Badge color="info" size="sm"
                        >{{ flow.edges?.length ?? 0 }} Conexões</Badge
                    >
                </div>

                <p class="mt-3 text-[11px] text-content-subtle">
                    Editado em {{ formatDateTime(flow.updated_at) }}
                </p>

                <Button
                    size="sm"
                    variant="secondary"
                    :icon="Pencil"
                    :href="`/fluxos/${flow.id}`"
                    class="mt-3 w-full justify-center"
                >
                    Abrir Construtor
                </Button>
            </Card>
        </div>

        <Card v-else>
            <EmptyState
                :icon="Workflow"
                title="Nenhum Fluxo"
                description="Monte um atendimento automático arrastando blocos na tela."
            />
        </Card>
    </div>

    <Modal
        :open="showForm"
        title="Novo Fluxo"
        description="O construtor abre com um bloco de início."
        @close="showForm = false"
    >
        <form id="flow-form" class="space-y-4" @submit.prevent="submit">
            <FormField label="Nome" :error="form.errors.name" required>
                <TextInput
                    v-model="form.name"
                    :invalid="Boolean(form.errors.name)"
                />
            </FormField>

            <FormField label="Descrição" :error="form.errors.description">
                <TextArea v-model="form.description" rows="2" />
            </FormField>
        </form>

        <template #footer>
            <Button variant="secondary" @click="showForm = false"
                >Cancelar</Button
            >
            <Button type="submit" form="flow-form" :loading="form.processing"
                >Criar</Button
            >
        </template>
    </Modal>

    <ConfirmDialog
        :open="deleting !== null"
        title="Excluir Fluxo"
        :message="`O fluxo ${deleting?.name} será removido das conexões que o utilizam.`"
        confirm-label="Excluir"
        @close="deleting = null"
        @confirm="destroy"
    />
</template>
