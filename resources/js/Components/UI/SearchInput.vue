<script setup>
import { Search } from "lucide-vue-next";

defineProps({
    modelValue: { type: String, default: "" },
    placeholder: { type: String, default: "Pesquisar" },
});

defineEmits(["update:modelValue"]);
</script>

<template>
    <div class="relative">
        <Search
            :size="16"
            class="absolute left-3 top-1/2 -translate-y-1/2 text-content-subtle"
        />

        <input
            :value="modelValue"
            :placeholder="Placeholder"
            type="search"
            class="h-9 w-full rounded-control border border-border bg-surface pl-9 pr-3 text-sm text-content placeholder:text-content-subtle focus:border-primary focus:outline-none"
            @input="$emit('update:modelValue', $event.target.value)"
        />
    </div>
</template>
