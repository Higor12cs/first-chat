<script setup>
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: null },
});
</script>

<template>
    <header class="flex flex-wrap items-end justify-between gap-4">
        <div class="space-y-1">
            <h1 class="text-xl font-semibold tracking-tight text-content">
                {{ title }}
            </h1>
            <p v-if="subtitle" class="text-sm text-content-muted">
                {{ subtitle }}
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <slot name="actions" />
        </div>
    </header>
</template>
