<script setup>
import { Inbox } from "lucide-vue-next";

defineProps({
    icon: { type: [Object, Function], default: () => Inbox },
    title: { type: String, required: true },
    description: { type: String, default: null },
});
</script>

<template>
    <div
        class="flex flex-col items-center justify-center gap-3 px-6 py-12 text-center"
    >
        <span class="rounded-full bg-surface-muted p-3 text-content-subtle">
            <component :is="icon" :size="22" />
        </span>

        <div class="space-y-1">
            <p class="text-sm font-medium text-content">{{ title }}</p>
            <p v-if="description" class="text-xs text-content-muted">
                {{ description }}
            </p>
        </div>

        <slot />
    </div>
</template>
