<script setup>
import { Link } from "@inertiajs/vue3";
import { ArrowDown, ArrowUp, Bot, Clock, Users } from "lucide-vue-next";
import Avatar from "../UI/Avatar.vue";
import Badge from "../UI/Badge.vue";
import { formatRelative } from "../../Utils/format";
import { formatWhatsApp } from "../../Utils/whatsapp";
import { usePermissions } from "../../Composables/usePermissions";

import { computed } from "vue";

const props = defineProps({
    conversation: { type: Object, required: true },
    active: { type: Boolean, default: false },
    now: { type: Number, default: () => Date.now() },
});

const emit = defineEmits(["context", "take"]);

const partialProps = ["selected", "messages", "timeline"];

const resetHistory = { "X-Inertia-Reset": "messages" };

const { can } = usePermissions();

const canTake = computed(
    () =>
        props.conversation.section === "waiting" && can("conversations.reply"),
);

const age = computed(() =>
    formatRelative(props.conversation.last_message_at, props.now),
);

const lastMessage = computed(() => props.conversation.last_message ?? null);

const preview = computed(() => {
    if (!lastMessage.value) {
        return "Sem mensagens.";
    }

    if (lastMessage.value.status === "deleted") {
        return "Mensagem excluída";
    }

    return (
        formatWhatsApp(lastMessage.value.body) || lastMessage.value.type_label
    );
});

const directionIcon = computed(() => {
    if (!lastMessage.value) {
        return null;
    }

    return lastMessage.value.direction === "outbound" ? ArrowUp : ArrowDown;
});
</script>

<template>
    <Link
        :href="`/atendimentos/${conversation.id}`"
        :only="partialProps"
        :headers="resetHistory"
        preserve-state
        preserve-scroll
        prefetch="hover"
        cache-for="5s"
        :show-progress="false"
        class="flex gap-2.5 border-b border-border px-3 py-2.5 transition"
        :class="active ? 'bg-primary-soft' : 'hover:bg-surface-muted'"
        @contextmenu.prevent="emit('context', { conversation, event: $event })"
    >
        <div class="relative shrink-0">
            <Avatar
                :name="conversation.contact?.name"
                :src="conversation.contact?.avatar_url"
                :size="38"
            />

            <span
                v-if="conversation.is_group"
                class="absolute -bottom-0.5 -right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-surface text-content-muted"
            >
                <Users :size="10" />
            </span>

            <span
                v-else-if="
                    conversation.status === 'bot' ||
                    conversation.status === 'ai'
                "
                class="absolute -bottom-0.5 -right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-surface text-info"
            >
                <Bot :size="10" />
            </span>
        </div>

        <div class="min-w-0 flex-1 space-y-0.5">
            <div class="flex items-center justify-between gap-2">
                <p class="truncate text-sm font-medium text-content">
                    {{ conversation.contact?.name }}
                </p>
                <span class="shrink-0 text-[11px] text-content-subtle">{{
                    age
                }}</span>
            </div>

            <div class="flex items-center gap-1 text-xs text-content-muted">
                <component
                    :is="directionIcon"
                    v-if="directionIcon"
                    :size="12"
                    class="shrink-0"
                />
                <span class="truncate [&_code]:font-mono" v-html="preview" />
            </div>

            <div
                v-if="
                    conversation.service_queue ||
                    conversation.assigned_user ||
                    conversation.tags?.length
                "
                class="flex flex-wrap items-center gap-1 pt-0.5"
            >
                <Badge
                    v-if="conversation.service_queue"
                    color="muted"
                    size="sm"
                >
                    {{ conversation.service_queue.name }}
                </Badge>
                <Badge v-if="conversation.assigned_user" color="info" size="sm">
                    {{ conversation.assigned_user.name }}
                </Badge>
                <Badge
                    v-for="tag in conversation.tags"
                    :key="tag.id"
                    :color="tag.color"
                    size="sm"
                    >{{ tag.name }}</Badge
                >
            </div>
        </div>

        <div class="flex shrink-0 flex-col items-end gap-1">
            <span
                v-if="conversation.unread_count > 0"
                class="flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1.5 text-[11px] font-semibold text-primary-content"
            >
                {{ conversation.unread_count }}
            </span>

            <Clock
                v-if="conversation.no_action_at"
                :size="12"
                class="text-content-subtle"
            />

            <button
                v-if="canTake"
                type="button"
                class="mt-auto rounded-control border border-border px-2 py-1 text-[11px] font-medium text-content-muted transition hover:border-primary hover:bg-primary-soft hover:text-primary"
                @click.prevent.stop="emit('take', conversation)"
            >
                Assumir
            </button>
        </div>
    </Link>
</template>
