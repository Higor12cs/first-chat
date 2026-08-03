<script setup>
import { computed } from "vue";
import { Link } from "@inertiajs/vue3";
import { RefreshCw } from "lucide-vue-next";

const props = defineProps({
    variant: { type: String, default: "primary" },
    size: { type: String, default: "md" },
    icon: { type: [Object, Function], default: null },
    href: { type: String, default: null },
    type: { type: String, default: "button" },
    loading: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

const variants = {
    primary:
        "bg-primary text-primary-content hover:bg-primary-hover border-transparent",
    secondary: "bg-surface text-content hover:bg-surface-hover border-border",
    ghost: "bg-transparent text-content-muted hover:bg-surface-hover hover:text-content border-transparent",
    danger: "bg-danger text-white hover:bg-danger-hover border-transparent",
    soft: "bg-primary-soft text-primary hover:brightness-95 border-transparent",
};

const sizes = {
    sm: "h-8 px-2.5 text-xs gap-1.5",
    md: "h-9 px-3.5 text-sm gap-2",
    lg: "h-11 px-5 text-sm gap-2",
    icon: "h-9 w-9 justify-center",
};

const classes = computed(() => [
    "inline-flex items-center rounded-control border font-medium transition select-none",
    "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary",
    "disabled:opacity-50 disabled:pointer-events-none",
    variants[props.variant] ?? variants.primary,
    sizes[props.size] ?? sizes.md,
]);

const component = computed(() => (props.href ? Link : "button"));
</script>

<template>
    <component
        :is="component"
        :href="href"
        :type="href ? undefined : type"
        :disabled="disabled || loading"
        :class="classes"
    >
        <RefreshCw v-if="loading" :size="16" class="animate-spin" />
        <component :is="icon" v-else-if="icon" :size="16" />
        <slot />
    </component>
</template>
