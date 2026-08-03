<script setup>
import { computed } from "vue";
import { TriangleAlert } from "lucide-vue-next";

const props = defineProps({
    level: { type: String, default: "warning" },
});

const tones = {
    danger: "bg-danger-soft text-danger",
    warning: "bg-warning-soft text-warning",
};

const tone = computed(() => tones[props.level] ?? tones.warning);
</script>

<template>
    <p
        class="flex items-start gap-2 rounded-control px-2.5 py-1.5 text-xs"
        :class="tone"
    >
        <TriangleAlert :size="14" class="mt-px shrink-0" />

        <span class="min-w-0 flex-1"><slot /></span>

        <slot name="action" />
    </p>
</template>
