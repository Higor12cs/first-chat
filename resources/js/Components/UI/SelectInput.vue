<script setup>
defineProps({
    modelValue: { type: [String, Number, null], default: null },
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: null },
    invalid: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

defineEmits(["update:modelValue"]);
</script>

<template>
    <div class="relative">
        <select
            :value="modelValue ?? ''"
            :disabled="disabled"
            class="h-9 w-full appearance-none rounded-control border bg-surface px-3 pr-8 text-sm text-content transition focus:border-primary focus:outline-none disabled:opacity-60"
            :class="invalid ? 'border-danger' : 'border-border'"
            @change="
                $emit(
                    'update:modelValue',
                    $event.target.value === '' ? null : $event.target.value,
                )
            "
        >
            <option v-if="placeholder" value="">{{ placeholder }}</option>
            <option
                v-for="option in options"
                :key="option.value"
                :value="option.value"
            >
                {{ option.label }}
            </option>
        </select>

        <svg
            class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-content-subtle"
            width="16"
            height="16"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            stroke-width="1.6"
        >
            <path d="M6 9l6 6 6-6" />
        </svg>
    </div>
</template>
