<script setup>
import { computed } from "vue";
import { Bot, ChevronDown, Clock, Headset, Moon, Users } from "lucide-vue-next";
import ConversationListItem from "./ConversationListItem.vue";

const props = defineProps({
    section: { type: Object, required: true },
    selectedId: { type: String, default: null },
    collapsed: { type: Boolean, default: false },
    now: { type: Number, default: () => Date.now() },
});

const emit = defineEmits(["toggle", "context", "take"]);

const icons = {
    bot: Bot,
    moon: Moon,
    clock: Clock,
    headset: Headset,
    users: Users,
};

const tones = {
    info: "text-info",
    muted: "text-content-subtle",
    warning: "text-warning",
    success: "text-success",
    primary: "text-primary",
};

const icon = computed(() => icons[props.section.icon] ?? Clock);
const tone = computed(() => tones[props.section.color] ?? "text-content-muted");
const isEmpty = computed(() => props.section.conversations.length === 0);
</script>

<template>
    <section class="border-b border-border">
        <button
            type="button"
            class="flex w-full items-center gap-2.5 bg-surface-muted px-3 py-3 text-left transition hover:bg-surface-hover"
            :title="section.description"
            @click="emit('toggle', section.value)"
        >
            <component :is="icon" :size="18" class="shrink-0" :class="tone" />

            <span class="flex-1 truncate text-sm font-semibold text-content">
                {{ section.label }}
            </span>

            <span
                v-if="section.unread > 0"
                class="flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1.5 text-[11px] font-semibold text-primary-content"
            >
                {{ section.unread }}
            </span>

            <span class="text-xs tabular-nums text-content-subtle">{{
                section.total
            }}</span>

            <ChevronDown
                :size="16"
                class="shrink-0 text-content-subtle transition-transform duration-200"
                :class="collapsed ? '-rotate-90' : 'rotate-0'"
            />
        </button>

        <div v-show="!collapsed">
            <p v-if="isEmpty" class="px-3 py-2.5 text-xs text-content-subtle">
                Nenhum atendimento aqui.
            </p>

            <ConversationListItem
                v-for="conversation in section.conversations"
                :key="conversation.id"
                :conversation="conversation"
                :active="selectedId === conversation.id"
                :now="now"
                @context="emit('context', $event)"
                @take="emit('take', $event)"
            />
        </div>
    </section>
</template>
