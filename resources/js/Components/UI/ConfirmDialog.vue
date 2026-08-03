<script setup>
import Modal from "./Modal.vue";
import Button from "./Button.vue";

defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: "Confirmar Ação" },
    message: { type: String, default: "Esta ação não pode ser desfeita." },
    confirmLabel: { type: String, default: "Confirmar" },
    processing: { type: Boolean, default: false },
});

const emit = defineEmits(["close", "confirm"]);
</script>

<template>
    <Modal :open="open" :title="title" size="sm" @close="emit('close')">
        <p class="text-sm text-content-muted">{{ message }}</p>

        <template #footer>
            <Button variant="secondary" @click="emit('close')">Cancelar</Button>
            <Button
                variant="danger"
                :loading="processing"
                @click="emit('confirm')"
                >{{ confirmLabel }}</Button
            >
        </template>
    </Modal>
</template>
