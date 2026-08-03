<script setup>
import { computed, onMounted, ref, watch } from "vue";
import { vMaska } from "maska/vue";

const props = defineProps({
    modelValue: { type: String, default: "" },
    withCountry: { type: Boolean, default: false },
    placeholder: { type: String, default: null },
    disabled: { type: Boolean, default: false },
    invalid: { type: Boolean, default: false },
});

const emit = defineEmits(["update:modelValue"]);

const input = ref(null);

const digits = computed(() => (props.modelValue ?? "").replace(/\D/g, ""));

const brazilian = computed(
    () => digits.value.length < 2 || digits.value.startsWith("55"),
);

const mask = computed(() => {
    if (!props.withCountry) {
        return ["(##) ####-####", "(##) #####-####"];
    }

    return brazilian.value
        ? ["+## (##) ####-####", "+## (##) #####-####"]
        : "+###############";
});

function onMaska(detail) {
    if (detail.unmasked !== digits.value) {
        emit("update:modelValue", detail.unmasked);
    }
}

const options = computed(() => ({ mask: mask.value, onMaska }));

function applyExternal(value) {
    const element = input.value;
    const raw = (value ?? "").replace(/\D/g, "");

    if (!element || raw === element.value.replace(/\D/g, "")) {
        return;
    }

    element.value = raw;
    element.dispatchEvent(new Event("input"));
}

onMounted(() => applyExternal(props.modelValue));

watch(() => props.modelValue, applyExternal);
</script>

<template>
    <input
        ref="input"
        v-maska="options"
        type="tel"
        inputmode="tel"
        :placeholder="Placeholder"
        :disabled="disabled"
        class="h-9 w-full rounded-control border bg-surface px-3 text-sm text-content transition placeholder:text-content-subtle focus:border-primary focus:outline-none disabled:opacity-60"
        :class="invalid ? 'border-danger' : 'border-border'"
    />
</template>
