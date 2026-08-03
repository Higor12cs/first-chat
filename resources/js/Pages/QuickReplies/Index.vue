<script setup>
import { computed, ref, watch } from "vue";
import { Head, router, useForm } from "@inertiajs/vue3";
import PageHeader from "../../Components/UI/PageHeader.vue";
import Card from "../../Components/UI/Card.vue";
import Button from "../../Components/UI/Button.vue";
import Modal from "../../Components/UI/Modal.vue";
import ConfirmDialog from "../../Components/UI/ConfirmDialog.vue";
import FormField from "../../Components/UI/FormField.vue";
import TextInput from "../../Components/UI/TextInput.vue";
import TextArea from "../../Components/UI/TextArea.vue";
import SelectInput from "../../Components/UI/SelectInput.vue";
import SearchInput from "../../Components/UI/SearchInput.vue";
import Toggle from "../../Components/UI/Toggle.vue";
import Badge from "../../Components/UI/Badge.vue";
import { Pencil, Plus, Star, StickyNote, Trash2 } from "lucide-vue-next";
import EmptyState from "../../Components/UI/EmptyState.vue";
import { usePermissions } from "../../Composables/usePermissions";

const props = defineProps({
    quick_replies: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
});

const { can } = usePermissions();

const search = ref(props.filters.search ?? "");
const category = ref(props.filters.category ?? null);

const showForm = ref(false);
const editing = ref(null);
const deleting = ref(null);

const categoryOptions = computed(() => props.categories.map((item) => ({ value: item, label: item })));

const variableHint = "{{contato.nome}}";

const form = useForm({
    title: "",
    shortcut: "",
    category: "",
    body: "",
    is_favorite: false,
    is_shared: true,
});

let searchTimeout = null;

function applyFilters() {
    router.get(
        "/respostas-rapidas",
        { search: search.value || undefined, category: category.value || undefined },
        { preserveState: true, replace: true, only: ["quick_replies", "filters"] },
    );
}

watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(applyFilters, 350);
});

watch(category, applyFilters);

function openForm(reply = null) {
    editing.value = reply;

    form.defaults({
        title: reply?.title ?? "",
        shortcut: reply?.shortcut ?? "",
        category: reply?.category ?? "",
        body: reply?.body ?? "",
        is_favorite: reply?.is_favorite ?? false,
        is_shared: reply?.is_shared ?? true,
    });

    form.reset();
    form.clearErrors();
    showForm.value = true;
}

function submit() {
    const options = { preserveScroll: true, onSuccess: () => (showForm.value = false) };

    if (editing.value) {
        form.put(`/respostas-rapidas/${editing.value.id}`, options);
    } else {
        form.post("/respostas-rapidas", options);
    }
}

function toggleFavorite(reply) {
    router.post(`/respostas-rapidas/${reply.id}/favorito`, {}, { preserveScroll: true });
}

function destroy() {
    router.delete(`/respostas-rapidas/${deleting.value.id}`, {
        preserveScroll: true,
        onFinish: () => (deleting.value = null),
    });
}
</script>

<template>
    <Head title="Respostas Rápidas" />

    <div class="space-y-5 p-4 lg:p-6">
        <PageHeader
            title="Respostas Rápidas"
            subtitle="Textos prontos acionados com / durante o atendimento."
        >
            <template #actions>
                <div class="flex flex-wrap items-center gap-2">
                    <SearchInput v-model="search" placeholder="Buscar resposta" class="w-52" />
                    <SelectInput v-model="category" :options="categoryOptions" placeholder="Todas as categorias" />
                    <Button v-if="can('quick-replies.create')" :icon="Plus" @click="openForm()">Nova Resposta</Button>
                </div>
            </template>
        </PageHeader>

        <div v-if="quick_replies.length" class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            <Card v-for="reply in quick_replies" :key="reply.id">
                <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0 space-y-1">
                        <p class="truncate text-sm font-semibold text-content">{{ reply.title }}</p>
                        <div class="flex flex-wrap gap-1">
                            <Badge color="primary" size="sm">{{ reply.shortcut }}</Badge>
                            <Badge v-if="reply.category" color="muted" size="sm">{{ reply.category }}</Badge>
                            <Badge :color="reply.is_shared ? 'info' : 'muted'" size="sm">
                                {{ reply.is_shared ? "Compartilhada" : "Pessoal" }}
                            </Badge>
                        </div>
                    </div>

                    <div class="flex shrink-0 gap-1">
                        <button
                            type="button"
                            class="rounded-control p-1.5 transition hover:bg-surface-hover"
                            :class="reply.is_favorite ? 'text-warning' : 'text-content-subtle'"
                            title="Favoritar"
                            @click="toggleFavorite(reply)"
                        >
                            <Star :size="16" />
                        </button>
                        <button
                            v-if="can('quick-replies.update')"
                            type="button"
                            class="rounded-control p-1.5 text-content-subtle transition hover:bg-surface-hover hover:text-content"
                            @click="openForm(reply)"
                        >
                            <Pencil :size="16" />
                        </button>
                        <button
                            v-if="can('quick-replies.delete')"
                            type="button"
                            class="rounded-control p-1.5 text-content-subtle transition hover:bg-danger-soft hover:text-danger"
                            @click="deleting = reply"
                        >
                            <Trash2 :size="16" />
                        </button>
                    </div>
                </div>

                <p class="mt-3 line-clamp-4 whitespace-pre-wrap text-xs text-content-muted">{{ reply.body }}</p>

                <p class="mt-3 text-[11px] text-content-subtle">Usada {{ reply.usage_count ?? 0 }} vezes.</p>
            </Card>
        </div>

        <Card v-else>
            <EmptyState
                :icon="StickyNote"
                title="Nenhuma Resposta Rápida"
                description="Crie atalhos para as mensagens que a sua equipe mais envia."
            />
        </Card>
    </div>

    <Modal
        :open="showForm"
        :title="editing ? 'Editar Resposta Rápida' : 'Nova Resposta Rápida'"
        :description="`Use ${variableHint} para personalizar o texto.`"
        @close="showForm = false"
    >
        <form id="quick-reply-form" class="space-y-4" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <FormField label="Título" :error="form.errors.title" required>
                    <TextInput v-model="form.title" :invalid="Boolean(form.errors.title)" />
                </FormField>

                <FormField label="Atalho" :error="form.errors.shortcut" hint="Digite / no atendimento para buscar." required>
                    <TextInput v-model="form.shortcut" placeholder="/Saudacao" :invalid="Boolean(form.errors.shortcut)" />
                </FormField>
            </div>

            <FormField label="Categoria" :error="form.errors.category">
                <TextInput v-model="form.category" placeholder="Comercial" />
            </FormField>

            <FormField label="Mensagem" :error="form.errors.body" required>
                <TextArea v-model="form.body" rows="5" :invalid="Boolean(form.errors.body)" />
            </FormField>

            <div class="space-y-3">
                <Toggle v-model="form.is_shared" label="Compartilhar com a equipe" />
                <Toggle v-model="form.is_favorite" label="Marcar como favorita" />
            </div>
        </form>

        <template #footer>
            <Button variant="secondary" @click="showForm = false">Cancelar</Button>
            <Button type="submit" form="quick-reply-form" :loading="form.processing">Salvar</Button>
        </template>
    </Modal>

    <ConfirmDialog
        :open="deleting !== null"
        title="Excluir Resposta Rápida"
        :message="`A resposta ${deleting?.title} será removida.`"
        confirm-label="Excluir"
        @close="deleting = null"
        @confirm="destroy"
    />
</template>
