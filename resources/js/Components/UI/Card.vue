<script setup>
defineProps({
    title: { type: String, default: null },
    description: { type: String, default: null },
    padded: { type: Boolean, default: true },
});
</script>

<template>
    <section class="rounded-card border border-border bg-surface">
        <header
            v-if="title || $slots.actions"
            class="flex items-center justify-between gap-4 border-b border-border px-4 py-3"
        >
            <div class="space-y-0.5">
                <h2 class="text-sm font-semibold text-content">{{ title }}</h2>
                <p v-if="description" class="text-xs text-content-muted">
                    {{ description }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <slot name="actions" />
            </div>
        </header>

        <div :class="padded ? 'p-4' : ''">
            <slot />
        </div>
    </section>
</template>
