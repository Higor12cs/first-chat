<script setup>
import { computed } from "vue";

const props = defineProps({
    color: { type: String, default: "muted" },
    size: { type: String, default: "md" },
});

const colors = {
    primary: "bg-primary-soft text-primary",
    success: "bg-success-soft text-success",
    warning: "bg-warning-soft text-warning",
    danger: "bg-danger-soft text-danger",
    info: "bg-info-soft text-info",
    muted: "bg-surface-muted text-content-muted",
};

const classes = computed(() => [
    "inline-flex items-center gap-1 rounded-full font-medium whitespace-nowrap",
    props.size === "sm" ? "px-1.5 py-0.5 text-[11px]" : "px-2 py-0.5 text-xs",
    colors[props.color] ?? colors.muted,
]);
</script>

<template>
    <span :class="classes"><slot /></span>
</template>
