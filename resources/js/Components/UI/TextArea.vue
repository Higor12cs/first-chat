<script setup>
defineProps({
    modelValue: { type: String, default: "" },
    rows: { type: [Number, String], default: 4 },
    placeholder: { type: String, default: null },
    invalid: { type: Boolean, default: false },
});

defineEmits(["update:modelValue"]);
</script>

<template>
    <textarea
        :value="modelValue"
        :rows="rows"
        :placeholder="Placeholder"
        class="w-full rounded-control border bg-surface px-3 py-2 text-sm text-content transition placeholder:text-content-subtle focus:border-primary focus:outline-none"
        :class="invalid ? 'border-danger' : 'border-border'"
        @input="$emit('update:modelValue', $event.target.value)"
    />
</template>
