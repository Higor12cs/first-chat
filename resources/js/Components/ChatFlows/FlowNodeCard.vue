<script setup>
import { computed } from "vue";
import { Check, CircleCheck, Info, ListChecks, MessagesSquare, Play, Sparkles, Users, Workflow, X } from "lucide-vue-next";
import { NODE_WIDTH } from "../../Composables/useFlowCanvas";

const props = defineProps({
    node: { type: Object, required: true },
    handles: { type: Array, default: () => [] },
    selected: { type: Boolean, default: false },
    connecting: { type: Boolean, default: false },
    activeHandle: { type: String, default: null },
});

const emit = defineEmits(["drag-start", "select", "connect-from", "connect-to", "remove"]);

const icons = {
    start: Play,
    message: MessagesSquare,
    menu: ListChecks,
    question: Info,
    condition: Workflow,
    ai: Sparkles,
    queue: Users,
    close: CircleCheck,
    end: Check,
};

const tones = {
    start: "bg-success-soft text-success",
    message: "bg-primary-soft text-primary",
    menu: "bg-info-soft text-info",
    question: "bg-info-soft text-info",
    condition: "bg-warning-soft text-warning",
    ai: "bg-primary-soft text-primary",
    queue: "bg-surface-muted text-content-muted",
    close: "bg-danger-soft text-danger",
    end: "bg-danger-soft text-danger",
};

const width = NODE_WIDTH;

const summary = computed(() => {
    const data = props.node.data ?? {};

    if (props.node.type === "condition") {
        return `${data.field ?? "campo"} ${data.operator ?? "equals"} ${data.value ?? ""}`;
    }

    if (props.node.type === "menu") {
        return `${(data.options ?? []).length} opções`;
    }

    return data.text || data.label || "Sem conteúdo.";
});
</script>

<template>
    <div
        class="absolute select-none rounded-card border bg-surface transition-colors"
        :class="selected ? 'border-primary' : 'border-border'"
        :style="{ left: `${node.position.x}px`, top: `${node.position.y}px`, width: `${width}px` }"
        @mousedown.stop="emit('drag-start', $event)"
        @click.stop="emit('select')"
    >
        <header class="flex items-center gap-2 rounded-t-card border-b border-border px-2.5 py-1.5">
            <span class="rounded p-1" :class="tones[node.type] ?? 'bg-surface-muted text-content-muted'">
                <component :is="icons[node.type] ?? Workflow" :size="13" />
            </span>

            <span class="min-w-0 flex-1 truncate text-xs font-medium text-content">
                {{ node.data?.label ?? node.type }}
            </span>

            <button
                v-if="node.type !== 'start'"
                type="button"
                class="rounded p-0.5 text-content-subtle transition hover:bg-danger-soft hover:text-danger"
                @mousedown.stop
                @click.stop="emit('remove')"
            >
                <X :size="12" />
            </button>
        </header>

        <p class="line-clamp-2 px-2.5 py-1.5 text-[11px] text-content-muted">{{ summary }}</p>

        <div class="space-y-1 px-2.5 pb-2">
            <div v-for="handle in handles" :key="handle.id" class="flex items-center justify-end gap-1.5">
                <span class="truncate text-[10px] text-content-subtle">{{ handle.label }}</span>
                <button
                    type="button"
                    class="h-2.5 w-2.5 rounded-full border transition"
                    :class="
                        activeHandle === handle.id
                            ? 'border-primary bg-primary'
                            : 'border-border-strong bg-surface hover:border-primary hover:bg-primary-soft'
                    "
                    title="Arraste Até Outro Bloco"
                    @mousedown.stop
                    @click.stop="emit('connect-from', handle.id)"
                />
            </div>
        </div>

        <button
            v-if="node.type !== 'start'"
            type="button"
            class="absolute -left-1.5 top-4 h-3 w-3 rounded-full border-2 border-surface transition"
            :class="connecting ? 'bg-primary ring-2 ring-primary-soft' : 'bg-border-strong'"
            title="Entrada"
            @mousedown.stop
            @click.stop="emit('connect-to')"
        />
    </div>
</template>
