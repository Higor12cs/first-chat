<script setup>
defineProps({
    modelValue: { type: Boolean, default: false },
    label: { type: String, default: null },
    description: { type: String, default: null },
    disabled: { type: Boolean, default: false },
});

defineEmits(["update:modelValue"]);
</script>

<template>
    <button
        type="button"
        class="flex w-full items-start gap-3 text-left disabled:pointer-events-none disabled:opacity-60"
        :disabled="disabled"
        @click="$emit('update:modelValue', !modelValue)"
    >
        <span
            class="mt-0.5 inline-flex h-5 w-9 shrink-0 items-center rounded-full border transition"
            :class="
                modelValue
                    ? 'border-primary bg-primary'
                    : 'border-border bg-surface-muted'
            "
        >
            <span
                class="h-3.5 w-3.5 rounded-full bg-surface transition"
                :class="modelValue ? 'translate-x-[18px]' : 'translate-x-[3px]'"
            />
        </span>

        <span v-if="label || description" class="space-y-0.5">
            <span class="block text-sm text-content">{{ label }}</span>
            <span
                v-if="description"
                class="block text-xs text-content-subtle"
                >{{ description }}</span
            >
        </span>
    </button>
</template>
