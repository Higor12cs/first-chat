<script setup>
import { computed } from "vue";
import { CheckCircle2, PlayCircle, Send } from "lucide-vue-next";
import { formatDateTime } from "../../Utils/format";

const props = defineProps({
    kind: { type: String, required: true },
    label: { type: String, default: null },
    at: { type: String, default: null },
});

const icons = {
    closed: CheckCircle2,
    transfer: Send,
};

const icon = computed(() => icons[props.kind] ?? PlayCircle);
</script>

<template>
    <div class="my-3 flex justify-center">
        <span
            class="flex items-center gap-1.5 rounded-full border border-border bg-surface px-3 py-1 text-[11px] text-content-muted"
        >
            <component :is="icon" :size="12" />
            {{ label }} {{ formatDateTime(at) }}
        </span>
    </div>
</template>
