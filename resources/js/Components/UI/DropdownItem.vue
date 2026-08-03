<script setup>
import { computed } from "vue";
import { Link } from "@inertiajs/vue3";

const props = defineProps({
    icon: { type: [Object, Function], default: null },
    href: { type: String, default: null },
    danger: { type: Boolean, default: false },
});

const component = computed(() => (props.href ? Link : "button"));
</script>

<template>
    <component
        :is="component"
        :href="href"
        :type="href ? undefined : 'button'"
        class="flex w-full items-center gap-2 rounded-control px-2.5 py-1.5 text-left text-sm transition hover:bg-surface-hover"
        :class="danger ? 'text-danger' : 'text-content'"
    >
        <component :is="icon" v-if="icon" :size="16" />
        <slot />
    </component>
</template>
