<script setup>
import { onBeforeUnmount, onMounted, watch } from "vue";
import { X } from "lucide-vue-next";

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: null },
    description: { type: String, default: null },
    size: { type: String, default: "md" },
});

const emit = defineEmits(["close"]);

const sizes = {
    sm: "max-w-md",
    md: "max-w-xl",
    lg: "max-w-3xl",
    xl: "max-w-5xl",
};

function onKeydown(event) {
    if (event.key === "Escape" && props.open) {
        emit("close");
    }
}

watch(
    () => props.open,
    (value) => {
        document.body.classList.toggle("overflow-hidden", value);
    },
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
            enter-from-class="opacity-0 [&_[data-panel]]:translate-y-3 [&_[data-panel]]:scale-95"
            leave-active-class="transition-opacity duration-150 ease-in"
            leave-to-class="opacity-0 [&_[data-panel]]:translate-y-3 [&_[data-panel]]:scale-95"
        >
            <div
                v-if="open"
                class="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto p-4 sm:p-8"
            >
                <div
                    class="fixed inset-0 bg-overlay backdrop-blur-[2px]"
                    @click="emit('close')"
                />

                <div
                    data-panel
                    class="relative z-10 w-full rounded-card border border-border-strong bg-surface transition duration-200 ease-out"
                    :class="sizes[size] ?? sizes.md"
                >
                    <header
                        class="flex items-start justify-between gap-4 border-b border-border px-5 py-4"
                    >
                        <div class="space-y-0.5">
                            <h2 class="text-base font-semibold text-content">
                                {{ title }}
                            </h2>
                            <p
                                v-if="description"
                                class="text-xs text-content-muted"
                            >
                                {{ description }}
                            </p>
                        </div>

                        <button
                            type="button"
                            class="rounded-control p-1 text-content-subtle transition hover:bg-surface-hover hover:text-content"
                            @click="emit('close')"
                        >
                            <X :size="18" />
                        </button>
                    </header>

                    <div
                        class="max-h-[70vh] overflow-y-auto scrollbar-thin px-5 py-4"
                    >
                        <slot />
                    </div>

                    <footer
                        v-if="$slots.footer"
                        class="flex justify-end gap-2 border-t border-border px-5 py-3"
                    >
                        <slot name="footer" />
                    </footer>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
