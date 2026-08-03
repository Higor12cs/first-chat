<script setup>
import { Link } from "@inertiajs/vue3";

defineProps({
    href: { type: String, required: true },
    label: { type: String, required: true },
    icon: { type: [Object, Function], default: null },
    active: { type: Boolean, default: false },
    collapsed: { type: Boolean, default: false },
    prefetch: { type: [Boolean, String, Array], default: false },
});
</script>

<template>
    <Link
        :href="href"
        :prefetch="prefetch"
        class="flex h-9 items-center rounded-control text-sm transition"
        :class="[
            collapsed ? 'w-9 justify-center px-0' : 'gap-2.5 px-2',
            active
                ? 'bg-primary-soft font-medium text-primary'
                : 'text-content-muted hover:bg-surface-hover hover:text-content',
        ]"
        :title="collapsed ? label : undefined"
        :aria-label="Label"
    >
        <component :is="icon" :size="18" class="shrink-0" />
        <span v-if="!collapsed" class="truncate">{{ label }}</span>
    </Link>
</template>
