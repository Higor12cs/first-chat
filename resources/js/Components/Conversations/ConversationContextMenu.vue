<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from "vue";
import { CheckCheck, Hand, Pencil, Send, Tag } from "lucide-vue-next";

const props = defineProps({
    open: { type: Boolean, default: false },
    x: { type: Number, default: 0 },
    y: { type: Number, default: 0 },
    conversation: { type: Object, default: null },
    canTransfer: { type: Boolean, default: false },
    canTake: { type: Boolean, default: false },
    canClose: { type: Boolean, default: false },
    canTag: { type: Boolean, default: false },
    canEditContact: { type: Boolean, default: false },
});

const emit = defineEmits(["close", "transfer", "take", "finish", "tags", "edit-contact"]);

const menu = ref(null);
const position = ref({ top: 0, left: 0 });

const isGroup = computed(() => Boolean(props.conversation?.is_group));
const isClosed = computed(() => props.conversation?.status === "closed");

const items = computed(() => [
    {
        key: "take",
        label: "Assumir Atendimento",
        icon: Hand,
        visible: props.canTake && !isGroup.value && !isClosed.value && !props.conversation?.assigned_user,
    },
    {
        key: "transfer",
        label: "Transferir Atendimento",
        icon: Send,
        visible: props.canTransfer && !isGroup.value && !isClosed.value,
    },
    {
        key: "tags",
        label: "Aplicar Tags",
        icon: Tag,
        visible: props.canTag,
    },
    {
        key: "edit-contact",
        label: "Editar Contato",
        icon: Pencil,
        visible: props.canEditContact,
    },
    {
        key: "finish",
        label: "Finalizar Atendimento",
        icon: CheckCheck,
        visible: props.canClose && !isClosed.value,
        danger: true,
    },
]);

const visibleItems = computed(() => items.value.filter((item) => item.visible));

function place() {
    const width = 232;
    const height = visibleItems.value.length * 36 + 12;

    position.value = {
        left: Math.min(props.x, window.innerWidth - width - 8),
        top: Math.min(props.y, window.innerHeight - height - 8),
    };
}

function onDocumentEvent(event) {
    if (props.open && !menu.value?.contains(event.target)) {
        emit("close");
    }
}

function onKeydown(event) {
    if (event.key === "Escape") {
        emit("close");
    }
}

watch(() => [props.open, props.x, props.y], place, { immediate: true });

onMounted(() => {
    document.addEventListener("mousedown", onDocumentEvent);
    document.addEventListener("keydown", onKeydown);
    window.addEventListener("resize", place);
    window.addEventListener("scroll", onDocumentEvent, true);
});

onBeforeUnmount(() => {
    document.removeEventListener("mousedown", onDocumentEvent);
    document.removeEventListener("keydown", onKeydown);
    window.removeEventListener("resize", place);
    window.removeEventListener("scroll", onDocumentEvent, true);
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-120 ease-out"
            enter-from-class="opacity-0 scale-95"
            leave-active-class="transition duration-100 ease-in"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="open && visibleItems.length"
                ref="menu"
                class="fixed z-50 w-58 origin-top-left rounded-card border border-border-strong bg-surface p-1"
                :style="{ top: `${position.top}px`, left: `${position.left}px` }"
            >
                <p class="truncate px-2.5 pb-1.5 pt-1 text-[11px] text-content-subtle">
                    {{ conversation?.contact?.name }}
                </p>

                <button
                    v-for="item in visibleItems"
                    :key="item.key"
                    type="button"
                    class="flex w-full items-center gap-2.5 rounded-control px-2.5 py-2 text-left text-sm transition"
                    :class="
                        item.danger
                            ? 'text-danger hover:bg-danger-soft'
                            : 'text-content hover:bg-surface-hover'
                    "
                    @click="emit(item.key)"
                >
                    <component :is="item.icon" :size="15" class="shrink-0" />
                    {{ item.label }}
                </button>
            </div>
        </Transition>
    </Teleport>
</template>
