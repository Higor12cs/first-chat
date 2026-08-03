<script setup>
import { computed, onBeforeUnmount, onMounted, watch } from "vue";
import { X } from "lucide-vue-next";

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: null },
    description: { type: String, default: null },
    side: { type: String, default: "right" },
    size: { type: String, default: "md" },
});

const emit = defineEmits(["close"]);

const sizes = {
    sm: "max-w-xs",
    md: "max-w-sm",
    lg: "max-w-md",
    xl: "max-w-2xl",
};

const fromRight = computed(() => props.side !== "left");

const offscreen = computed(() =>
    fromRight.value
        ? "[&_[data-panel]]:translate-x-full"
        : "[&_[data-panel]]:-translate-x-full",
);

const panelClasses = computed(() => [
    fromRight.value ? "border-l" : "border-r",
    sizes[props.size] ?? sizes.md,
]);

function onKeydown(event) {
    if (event.key === "Escape" && props.open) {
        emit("close");
    }
}

watch(
    () => props.open,
    (value) => document.body.classList.toggle("overflow-hidden", value),
);

onMounted(() => document.addEventListener("keydown", onKeydown));

onBeforeUnmount(() => {
    document.removeEventListener("keydown", onKeydown);
    document.body.classList.remove("overflow-hidden");
});
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-200 ease-out"
            :enter-from-class="`opacity-0 ${offscreen}`"
            leave-active-class="transition-opacity duration-200 ease-in"
            :leave-to-class="`opacity-0 ${offscreen}`"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-50 flex"
                :class="fromRight ? 'justify-end' : 'justify-start'"
            >
                <div
                    class="absolute inset-0 bg-overlay backdrop-blur-[2px]"
                    @click="emit('close')"
                />

                <aside
                    data-panel
                    class="relative z-10 flex h-full w-full flex-col border-border-strong bg-surface transition-transform duration-200 ease-out"
                    :class="panelClasses"
                >
                    <header
                        v-if="title || $slots.header"
                        class="flex shrink-0 items-start justify-between gap-4 border-b border-border px-4 py-3"
                    >
                        <slot name="header">
                            <div class="min-w-0 space-y-0.5">
                                <h2
                                    class="truncate text-sm font-semibold text-content"
                                >
                                    {{ title }}
                                </h2>
                                <p
                                    v-if="description"
                                    class="truncate text-xs text-content-muted"
                                >
                                    {{ description }}
                                </p>
                            </div>
                        </slot>

                        <button
                            type="button"
                            class="shrink-0 rounded-control p-1 text-content-subtle transition hover:bg-surface-hover hover:text-content"
                            aria-label="Fechar"
                            @click="emit('close')"
                        >
                            <X :size="18" />
                        </button>
                    </header>

                    <button
                        v-else
                        type="button"
                        class="absolute right-3 top-3 z-10 rounded-control p-1.5 text-content-subtle transition hover:bg-surface-hover hover:text-content"
                        aria-label="Fechar"
                        @click="emit('close')"
                    >
                        <X :size="18" />
                    </button>

                    <div class="min-h-0 flex-1 overflow-y-auto scrollbar-thin">
                        <slot />
                    </div>

                    <footer
                        v-if="$slots.footer"
                        class="flex shrink-0 justify-end gap-2 border-t border-border px-4 py-3"
                    >
                        <slot name="footer" />
                    </footer>
                </aside>
            </div>
        </Transition>
    </Teleport>
</template>
