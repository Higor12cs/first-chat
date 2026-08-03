<script setup>
defineProps({
    modelValue: { type: [String, Number], default: "" },
    type: { type: String, default: "text" },
    placeholder: { type: String, default: null },
    disabled: { type: Boolean, default: false },
    invalid: { type: Boolean, default: false },
});

defineEmits(["update:modelValue"]);
</script>

<template>
    <input
        :type="type"
        :value="modelValue"
        :placeholder="Placeholder"
        :disabled="disabled"
        class="h-9 w-full rounded-control border bg-surface px-3 text-sm text-content transition placeholder:text-content-subtle focus:border-primary focus:outline-none disabled:opacity-60"
        :class="invalid ? 'border-danger' : 'border-border'"
        @input="$emit('update:modelValue', $event.target.value)"
    />
</template>
