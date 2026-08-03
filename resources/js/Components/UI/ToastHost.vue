<script setup>
import { storeToRefs } from "pinia";
import { useToastStore } from "../../Stores/toast";
import { Check, TriangleAlert, X } from "lucide-vue-next";

const store = useToastStore();
const { items } = storeToRefs(store);

const tones = {
    success: "border-success text-success",
    warning: "border-warning text-warning",
    danger: "border-danger text-danger",
    info: "border-info text-info",
};
</script>

<template>
    <div
        class="pointer-events-none fixed bottom-4 right-4 z-[60] flex w-80 flex-col gap-2"
    >
        <TransitionGroup
            enter-active-class="transition duration-150"
            enter-from-class="translate-y-2 opacity-0"
            leave-active-class="transition duration-100"
            leave-to-class="opacity-0"
        >
            <div
                v-for="item in items"
                :key="item.id"
                class="pointer-events-auto flex items-start gap-2.5 rounded-card border-l-2 border border-border-strong bg-surface px-3.5 py-3"
                :class="tones[item.type] ?? tones.info"
            >
                <component
                    :is="
                        ['danger', 'warning'].includes(item.type)
                            ? TriangleAlert
                            : Check
                    "
                    :size="16"
                    class="mt-0.5"
                />
                <p class="flex-1 text-sm text-content">{{ item.message }}</p>

                <button
                    type="button"
                    class="text-content-subtle transition hover:text-content"
                    @click="store.dismiss(item.id)"
                >
                    <X :size="14" />
                </button>
            </div>
        </TransitionGroup>
    </div>
</template>
