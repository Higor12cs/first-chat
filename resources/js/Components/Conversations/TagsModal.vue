<script setup>
import { ref, watch } from "vue";
import { useForm } from "@inertiajs/vue3";
import Modal from "../UI/Modal.vue";
import Button from "../UI/Button.vue";
import EmptyState from "../UI/EmptyState.vue";
import { Tag } from "lucide-vue-next";

const props = defineProps({
    open: { type: Boolean, default: false },
    conversation: { type: Object, default: null },
    tags: { type: Array, default: () => [] },
});

const emit = defineEmits(["close", "saved"]);

const selected = ref([]);
const form = useForm({ tags: [] });

watch(
    () => [props.open, props.conversation?.id],
    () => {
        if (props.open) {
            selected.value =
                props.conversation?.tags?.map((tag) => tag.id) ?? [];
        }
    },
    { immediate: true },
);

function toggle(id) {
    selected.value = selected.value.includes(id)
        ? selected.value.filter((item) => item !== id)
        : [...selected.value, id];
}

function submit() {
    form.transform(() => ({ tags: selected.value })).put(
        `/atendimentos/${props.conversation.id}/tags`,
        {
            preserveScroll: true,
            preserveState: true,
            only: ["selected", "sections", "flash"],
            onSuccess: () => {
                emit("saved");
                emit("close");
            },
        },
    );
}
</script>

<template>
    <Modal
        :open="open"
        title="Aplicar Tags"
        :description="conversation?.contact?.name"
        size="sm"
        @close="emit('close')"
    >
        <div v-if="tags.length" class="flex flex-wrap gap-1.5">
            <button
                v-for="tag in tags"
                :key="tag.id"
                type="button"
                class="rounded-full border px-2.5 py-1 text-xs transition"
                :class="
                    selected.includes(tag.id)
                        ? 'border-primary bg-primary-soft text-primary'
                        : 'border-border text-content-muted hover:bg-surface-hover'
                "
                @click="toggle(tag.id)"
            >
                {{ tag.name }}
            </button>
        </div>

        <EmptyState
            v-else
            :icon="Tag"
            title="Nenhuma Tag"
            description="Cadastre tags para classificar os atendimentos."
        />

        <template #footer>
            <Button variant="secondary" @click="emit('close')">Cancelar</Button>
            <Button :loading="form.processing" @click="submit">Salvar</Button>
        </template>
    </Modal>
</template>
