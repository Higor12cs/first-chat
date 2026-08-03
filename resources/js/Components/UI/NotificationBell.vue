<script setup>
import { computed } from "vue";
import { Link } from "@inertiajs/vue3";
import { Bell } from "lucide-vue-next";
import Dropdown from "./Dropdown.vue";

const props = defineProps({
    alerts: { type: Array, default: () => [] },
});

const critical = computed(() =>
    props.alerts.some((alert) => alert.level === "danger"),
);

const tones = {
    danger: "bg-danger",
    warning: "bg-warning",
};
</script>

<template>
    <Dropdown width="w-80">
        <template #trigger>
            <button
                type="button"
                class="relative rounded-control p-1.5 text-content-muted transition hover:bg-surface-hover hover:text-content"
                title="Avisos"
            >
                <Bell :size="18" />

                <span
                    v-if="alerts.length"
                    class="absolute right-1 top-1 h-2 w-2 rounded-full ring-2 ring-surface"
                    :class="critical ? 'bg-danger' : 'bg-warning'"
                />
            </button>
        </template>

        <p
            v-if="!alerts.length"
            class="px-2.5 py-3 text-center text-xs text-content-subtle"
        >
            Nenhum aviso por agora.
        </p>

        <div
            v-for="alert in alerts"
            :key="alert.id"
            class="flex gap-2.5 rounded-control px-2.5 py-2"
        >
            <span
                class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
                :class="tones[alert.level] ?? tones.warning"
            />

            <div class="min-w-0 flex-1">
                <p class="text-sm font-medium text-content">
                    {{ alert.title }}
                </p>
                <p class="text-xs text-content-muted">{{ alert.message }}</p>

                <Link
                    v-if="alert.href"
                    :href="alert.href"
                    class="mt-1 inline-block text-xs font-medium text-primary underline-offset-2 hover:underline"
                >
                    {{ alert.action }}
                </Link>
            </div>
        </div>
    </Dropdown>
</template>
