<script setup>
import { computed, ref, watch } from "vue";
import { Link, router, useForm } from "@inertiajs/vue3";
import { Check, Clock, Pencil, RefreshCw, Send, Smartphone } from "lucide-vue-next";
import Avatar from "../UI/Avatar.vue";
import Badge from "../UI/Badge.vue";
import Button from "../UI/Button.vue";
import TextArea from "../UI/TextArea.vue";
import Toggle from "../UI/Toggle.vue";
import { formatDateTime, formatPhone } from "../../Utils/format";
import { usePermissions } from "../../Composables/usePermissions";

const props = defineProps({
    conversation: { type: Object, required: true },
    tags: { type: Array, default: () => [] },
    signing: { type: Boolean, default: false },
});

const emit = defineEmits([
    "transfer",
    "finish",
    "reopen",
    "edit-contact",
    "update:signing",
]);

const { can } = usePermissions();
const noteForm = useForm({ body: "" });

const selectedTags = ref(props.conversation.tags?.map((tag) => tag.id) ?? []);

watch(
    () => props.conversation.id,
    () => (selectedTags.value = props.conversation.tags?.map((tag) => tag.id) ?? []),
);

const closed = computed(() => props.conversation.status === "closed");

function toggleTag(tagId) {
    selectedTags.value = selectedTags.value.includes(tagId)
        ? selectedTags.value.filter((id) => id !== tagId)
        : [...selectedTags.value, tagId];

    router.put(
        `/atendimentos/${props.conversation.id}/tags`,
        { tags: selectedTags.value },
        { preserveScroll: true, preserveState: true, only: ["selected", "sections"] },
    );
}

function addNote() {
    noteForm.post(`/atendimentos/${props.conversation.id}/notas`, {
        preserveScroll: true,
        preserveState: true,
        only: ["selected"],
        onSuccess: () => noteForm.reset(),
    });
}
</script>

<template>
    <div class="flex h-full flex-col">
        <div class="flex flex-col items-center gap-2 border-b border-border px-4 py-5 text-center">
            <Avatar :name="conversation.contact?.name" :src="conversation.contact?.avatar_url" :size="60" />

            <div class="space-y-0.5">
                <p class="text-sm font-semibold text-content">{{ conversation.contact?.name }}</p>
                <p v-if="conversation.contact?.nickname" class="text-[11px] text-content-subtle">
                    WhatsApp: {{ conversation.contact?.legal_name }}
                </p>
                <p class="text-xs text-content-muted">
                    {{ formatPhone(conversation.contact?.phone) || conversation.contact?.email }}
                </p>
            </div>

            <div class="flex flex-wrap justify-center gap-1">
                <Badge :color="conversation.status_color">{{ conversation.section_label }}</Badge>
                <Badge v-if="conversation.service_queue" color="muted">{{ conversation.service_queue.name }}</Badge>
            </div>

            <div class="flex flex-wrap justify-center gap-2 pt-1">
                <button
                    v-if="can('contacts.update')"
                    type="button"
                    class="flex items-center gap-1 text-xs text-primary transition hover:underline"
                    @click="emit('edit-contact')"
                >
                    <Pencil :size="12" />
                    Editar Contato
                </button>

                <Link
                    v-if="can('contacts.view')"
                    :href="`/contatos/${conversation.contact?.id}`"
                    class="text-xs text-primary transition hover:underline"
                >
                    Ver Ficha Completa
                </Link>
            </div>
        </div>

        <div class="space-y-3 border-b border-border px-4 py-4">
            <div v-if="conversation.connection" class="space-y-1">
                <p class="text-xs font-medium text-content-muted">Canal</p>
                <p class="flex items-center gap-1.5 text-sm text-content">
                    <Smartphone :size="14" class="shrink-0 text-content-muted" />
                    {{ conversation.connection.name }}
                    <span
                        class="h-1.5 w-1.5 shrink-0 rounded-full"
                        :class="conversation.connection.status === 'connected' ? 'bg-success' : 'bg-warning'"
                        :title="conversation.connection.status_label"
                    />
                </p>
                <p class="text-xs text-content-subtle">
                    {{ formatPhone(conversation.contact_channel?.identifier) }}
                </p>
            </div>

            <div class="space-y-1">
                <p class="text-xs font-medium text-content-muted">Responsável</p>
                <p class="text-sm text-content">
                    {{ conversation.is_group ? "Grupo aberto ao time" : (conversation.assigned_user?.name ?? "Ninguém assumiu ainda") }}
                </p>
            </div>

            <Button
                v-if="can('conversations.assign') && !conversation.is_group"
                variant="secondary"
                size="sm"
                :icon="Send"
                class="w-full justify-center"
                @click="emit('transfer')"
            >
                Transferir Atendimento
            </Button>

            <div v-if="conversation.ai_objective" class="rounded-control bg-primary-soft px-3 py-2 text-xs text-primary">
                <p class="font-medium">Atendido pela IA</p>
                <p>{{ conversation.ai_objective.name }}</p>
            </div>
        </div>

        <div v-if="!closed" class="border-b border-border px-4 py-4">
            <Toggle
                :model-value="signing"
                label="Assinar mensagens"
                description="Vale só para este atendimento. Suas respostas saem com o seu nome na frente."
                @update:model-value="emit('update:signing', $event)"
            />
        </div>

        <div v-if="can('conversations.tags')" class="space-y-2 border-b border-border px-4 py-4">
            <p class="text-xs font-medium text-content-muted">Tags</p>

            <div class="flex flex-wrap gap-1.5">
                <button
                    v-for="tag in tags"
                    :key="tag.id"
                    type="button"
                    class="rounded-full border px-2 py-0.5 text-xs transition"
                    :class="
                        selectedTags.includes(tag.id)
                            ? 'border-primary bg-primary-soft text-primary'
                            : 'border-border text-content-muted hover:bg-surface-hover'
                    "
                    @click="toggleTag(tag.id)"
                >
                    {{ tag.name }}
                </button>

                <p v-if="!tags.length" class="text-xs text-content-subtle">Nenhuma tag cadastrada.</p>
            </div>
        </div>

        <div v-if="can('conversations.notes')" class="space-y-3 px-4 py-4">
            <p class="text-xs font-medium text-content-muted">Notas Internas</p>

            <form class="space-y-2" @submit.prevent="addNote">
                <TextArea v-model="noteForm.body" rows="2" placeholder="Registre uma observação para o time." />
                <Button type="submit" size="sm" variant="secondary" :loading="noteForm.processing" class="w-full justify-center">
                    Adicionar Nota
                </Button>
            </form>

            <div v-for="note in conversation.notes" :key="note.id" class="rounded-control bg-surface-muted p-2.5">
                <div class="flex items-center justify-between text-[11px] text-content-subtle">
                    <span>{{ note.user?.name ?? "Sistema" }}</span>
                    <span>{{ formatDateTime(note.created_at) }}</span>
                </div>
                <p class="mt-1 whitespace-pre-wrap text-xs text-content">{{ note.body }}</p>
            </div>
        </div>

        <div class="mt-auto space-y-2 border-t border-border px-4 py-4">
            <Button
                v-if="can('conversations.close') && !closed"
                variant="secondary"
                :icon="Check"
                class="w-full justify-center"
                @click="emit('finish')"
            >
                Finalizar Atendimento
            </Button>

            <Button
                v-else-if="can('conversations.close')"
                variant="secondary"
                :icon="RefreshCw"
                class="w-full justify-center"
                @click="emit('reopen')"
            >
                Reabrir Atendimento
            </Button>

            <p class="flex items-center justify-center gap-1 text-[11px] text-content-subtle">
                <Clock :size="12" />
                Criado em {{ formatDateTime(conversation.created_at) }}
            </p>
        </div>
    </div>
</template>
