<script setup>
import { onMounted } from "vue";
import { usePage } from "@inertiajs/vue3";
import { MessagesSquare } from "lucide-vue-next";
import ToastHost from "../Components/UI/ToastHost.vue";
import { useUiStore } from "../Stores/ui";

const page = usePage();
const ui = useUiStore();

onMounted(() => {
    ui.applyTheme();
    sessionStorage.removeItem("sidebar-auto-collapsed");
});
</script>

<template>
    <div
        class="flex min-h-screen items-center justify-center bg-canvas px-4 py-10"
    >
        <div class="w-full max-w-sm space-y-6">
            <div class="flex flex-col items-center gap-3 text-center">
                <span
                    class="flex h-11 w-11 items-center justify-center rounded-card bg-primary text-primary-content"
                >
                    <MessagesSquare :size="22" />
                </span>

                <div class="space-y-1">
                    <h1 class="text-lg font-semibold text-content">
                        {{ page.props.app.name }}
                    </h1>
                    <p class="text-sm text-content-muted">
                        Atendimento omnichannel para o seu time.
                    </p>
                </div>
            </div>

            <div class="rounded-card border border-border bg-surface p-6">
                <slot />
            </div>
        </div>

        <ToastHost />
    </div>
</template>
