<script setup>
import { ref, watch } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import PageHeader from "../../Components/UI/PageHeader.vue";
import Card from "../../Components/UI/Card.vue";
import Button from "../../Components/UI/Button.vue";
import Modal from "../../Components/UI/Modal.vue";
import ConfirmDialog from "../../Components/UI/ConfirmDialog.vue";
import FormField from "../../Components/UI/FormField.vue";
import TextInput from "../../Components/UI/TextInput.vue";
import TextArea from "../../Components/UI/TextArea.vue";
import SearchInput from "../../Components/UI/SearchInput.vue";
import Toggle from "../../Components/UI/Toggle.vue";
import Badge from "../../Components/UI/Badge.vue";
import { ClipboardList, Pencil, Plus, Trash2 } from "lucide-vue-next";
import EmptyState from "../../Components/UI/EmptyState.vue";
import { usePermissions } from "../../Composables/usePermissions";

const props = defineProps({
    cards: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const { can } = usePermissions();

const search = ref(props.filters.search ?? "");

const showForm = ref(false);
const editing = ref(null);
const deleting = ref(null);

const variableHint = "{{contato.nome}}";

const form = useForm({
    name: "",
    description: "",
    body: "",
    is_active: true,
});

let searchTimeout = null;

watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(
            "/cartoes",
            { search: search.value || undefined },
            { preserveState: true, replace: true, only: ["cards", "filters"] },
        );
    }, 350);
});

function openForm(card = null) {
    editing.value = card;

    form.defaults({
        name: card?.name ?? "",
        description: card?.description ?? "",
        body: card?.body ?? "",
        is_active: card?.is_active ?? true,
    });

    form.reset();
    form.clearErrors();
    showForm.value = true;
}

function submit() {
    const options = { preserveScroll: true, onSuccess: () => (showForm.value = false) };

    if (editing.value) {
        form.put(`/cartoes/${editing.value.id}`, options);
    } else {
        form.post("/cartoes", options);
    }
}

function destroy() {
    router.delete(`/cartoes/${deleting.value.id}`, {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
}
</script>

<template>
    <Head title="Cartões" />

    <div class="space-y-5 p-4 lg:p-6">
        <PageHeader
            title="Cartões"
            subtitle="Mensagens prontas que a plataforma envia sozinha, fora de hora ou pelo chatbot."
        >
            <template #actions>
                <div class="flex flex-wrap items-center gap-2">
                    <SearchInput v-model="search" placeholder="Buscar cartão" class="w-52" />
                    <Button v-if="can('cards.create')" :icon="Plus" @click="openForm()">Novo Cartão</Button>
                </div>
            </template>
        </PageHeader>

        <div v-if="cards.length" class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            <Card v-for="card in cards" :key="card.id">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0 space-y-1">
                        <p class="truncate text-sm font-semibold text-content">{{ card.name }}</p>
                        <Badge :color="card.is_active ? 'success' : 'muted'" size="sm">
                            {{ card.is_active ? "Ativo" : "Inativo" }}
                        </Badge>
                    </div>

                    <div class="flex shrink-0 gap-1">
                        <button
                            v-if="can('cards.update')"
                            type="button"
                            class="rounded-control p-1.5 text-content-subtle transition hover:bg-surface-hover hover:text-content"
                            @click="openForm(card)"
                        >
                            <Pencil :size="16" />
                        </button>
                        <button
                            v-if="can('cards.delete')"
                            type="button"
                            class="rounded-control p-1.5 text-content-subtle transition hover:bg-danger-soft hover:text-danger"
                            @click="deleting = card"
                        >
                            <Trash2 :size="16" />
                        </button>
                    </div>
                </div>

                <p v-if="card.description" class="mt-2 text-xs text-content-subtle">{{ card.description }}</p>

                <p class="mt-3 line-clamp-4 whitespace-pre-wrap text-xs text-content-muted">{{ card.body }}</p>
            </Card>
        </div>

        <Card v-else>
            <EmptyState
                :icon="ClipboardList"
                title="Nenhum Cartão"
                description="Crie cartões para responder fora do horário e dentro dos fluxos."
            />
        </Card>
    </div>

    <Modal
        :open="showForm"
        :title="editing ? 'Editar Cartão' : 'Novo Cartão'"
        :description="`Use ${variableHint} para personalizar o texto.`"
        @close="showForm = false"
    >
        <form id="card-form" class="space-y-4" @submit.prevent="submit">
            <FormField label="Nome" :error="form.errors.name" required>
                <TextInput v-model="form.name" :invalid="Boolean(form.errors.name)" />
            </FormField>

            <FormField label="Descrição" :error="form.errors.description" hint="Só a equipe enxerga esse texto.">
                <TextInput v-model="form.description" />
            </FormField>

            <FormField label="Mensagem" :error="form.errors.body" required>
                <TextArea v-model="form.body" rows="6" :invalid="Boolean(form.errors.body)" />
            </FormField>

            <Toggle
                v-model="form.is_active"
                label="Cartão ativo"
                description="Cartões inativos não são enviados nem aparecem nas seleções."
            />
        </form>

        <template #footer>
            <Button variant="secondary" @click="showForm = false">Cancelar</Button>
            <Button type="submit" form="card-form" :loading="form.processing">Salvar</Button>
        </template>
    </Modal>

    <ConfirmDialog
        :open="deleting !== null"
        title="Excluir Cartão"
        :message="`O cartão ${deleting?.name} deixará de ser enviado onde estiver selecionado.`"
        confirm-label="Excluir"
        @close="deleting = null"
        @confirm="destroy"
    />
</template>
