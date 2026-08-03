<script setup>
import { computed } from "vue";
import { Link } from "@inertiajs/vue3";

const props = defineProps({
    paginator: { type: Object, default: null },
});

const meta = computed(() => props.paginator?.meta ?? props.paginator ?? {});
</script>

<template>
    <nav
        v-if="meta.last_page > 1"
        class="flex items-center justify-between gap-4 px-4 py-3 text-xs text-content-muted"
    >
        <span
            >Mostrando {{ meta.from ?? 0 }} a {{ meta.to ?? 0 }} de
            {{ meta.total }} registros.</span
        >

        <div class="flex items-center gap-1">
            <Link
                v-for="link in meta.links"
                :key="link.label"
                :href="link.url ?? '#'"
                preserve-scroll
                preserve-state
                class="rounded-control px-2.5 py-1 transition"
                :class="[
                    link.active
                        ? 'bg-primary text-primary-content'
                        : 'hover:bg-surface-hover',
                    link.url ? '' : 'pointer-events-none opacity-40',
                ]"
                v-html="link.label"
            />
        </div>
    </nav>
</template>
