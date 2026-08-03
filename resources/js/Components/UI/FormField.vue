<script setup>
defineProps({
    label: { type: String, default: null },
    error: { type: String, default: null },
    hint: { type: String, default: null },
    required: { type: Boolean, default: false },
    group: { type: Boolean, default: false },
});
</script>

<template>
    <component :is="group ? 'div' : 'label'" class="block space-y-1.5">
        <span
            v-if="label"
            class="flex items-center gap-1 text-xs font-medium text-content-muted"
        >
            {{ label }}
            <span v-if="required" class="text-danger">*</span>
        </span>

        <slot />

        <span v-if="error" class="block text-xs text-danger">{{ error }}</span>
        <span v-else-if="hint" class="block text-xs text-content-subtle">{{
            hint
        }}</span>
    </component>
</template>
