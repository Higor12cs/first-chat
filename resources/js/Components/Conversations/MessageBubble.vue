<script setup>
import { computed, onBeforeUnmount, ref } from "vue";
import {
    Ban,
    Check,
    CheckCheck,
    Clock,
    Copy,
    EyeOff,
    RotateCcw,
    Trash2,
    TriangleAlert,
} from "lucide-vue-next";
import { formatTime } from "../../Utils/format";
import { formatWhatsApp } from "../../Utils/whatsapp";
import MediaAttachment from "./MediaAttachment.vue";

const props = defineProps({
    message: { type: Object, required: true },
    now: { type: Number, default: () => Date.now() },
    stallAfter: { type: Number, default: 12000 },
    manageable: { type: Boolean, default: false },
    contactName: { type: String, default: null },
});

const emit = defineEmits(["retry", "cancel", "delete"]);

const outbound = computed(() => props.message.direction === "outbound");
const deleted = computed(() => props.message.status === "deleted");

const state = computed(() => {
    if (deleted.value) {
        return "deleted";
    }

    if (!outbound.value) {
        return "received";
    }

    if (props.message.status === "canceled") {
        return "canceled";
    }

    if (props.message.status === "failed") {
        return "failed";
    }

    if (props.message.optimistic) {
        return props.now - props.message.queued_at > props.stallAfter
            ? "stalled"
            : "sending";
    }

    if (props.message.status === "pending") {
        const queuedAt = new Date(
            props.message.sent_at ?? props.message.created_at,
        ).getTime();

        return props.now - queuedAt > props.stallAfter ? "stalled" : "sending";
    }

    if (props.message.status === "delivered") {
        return "delivered";
    }

    return props.message.status === "read" ? "read" : "sent";
});

const statusIcon = computed(
    () =>
        ({
            sending: Check,
            stalled: Clock,
            failed: TriangleAlert,
            canceled: Ban,
            deleted: Trash2,
            sent: Check,
            delivered: CheckCheck,
            read: CheckCheck,
        })[state.value] ?? Check,
);

const statusLabel = computed(
    () =>
        ({
            sending: "Enviando",
            stalled: "Ainda não saiu",
            failed: "Não enviada",
            canceled: "Envio cancelado",
            deleted: "Mensagem excluída",
            sent: "Enviada",
            delivered: "Entregue",
            read: "Lida",
        })[state.value] ?? "",
);

const note = computed(() => {
    if (state.value === "canceled") {
        return "Envio Cancelado";
    }

    if (state.value !== "deleted") {
        return null;
    }

    if (outbound.value) {
        return "Mensagem Excluída";
    }

    return props.contactName === null
        ? "Excluída pelo Contato"
        : `Excluída por ${props.contactName}`;
});

const whisper = computed(() => Boolean(props.message.is_internal));

const tone = computed(() => {
    if (whisper.value && !["deleted", "canceled"].includes(state.value)) {
        return "border-dashed border-warning bg-warning-soft text-content";
    }

    return (
        {
            deleted: "border-danger bg-danger-soft text-content",
            canceled: "border-warning bg-warning-soft text-content",
            stalled: `border-warning ${outbound.value ? "bg-bubble-out" : "bg-bubble-in"} text-content`,
            failed: `border-danger ${outbound.value ? "bg-bubble-out" : "bg-bubble-in"} text-content`,
        }[state.value] ??
        (outbound.value
            ? "border-transparent bg-bubble-out text-content"
            : "border-border bg-bubble-in text-content")
    );
});

const quote = computed(() => {
    const replied = props.message.reply_to;

    if (!replied) {
        return null;
    }

    return {
        author: replied.author ?? props.contactName ?? "Contato",
        body: formatWhatsApp(replied.body) || replied.type_label,
    };
});

const body = computed(() => formatWhatsApp(props.message.body));

const stuck = computed(() => ["stalled", "failed"].includes(state.value));

const canCancel = computed(
    () =>
        props.manageable &&
        outbound.value &&
        !whisper.value &&
        ["sending", "stalled", "failed"].includes(state.value),
);
const canDelete = computed(
    () => props.manageable && outbound.value && !deleted.value,
);
const canCopy = computed(() => !deleted.value && Boolean(props.message.body));

const copied = ref(false);

let copiedTimer = null;

async function copyBody() {
    try {
        await navigator.clipboard.writeText(props.message.body);
    } catch {
        return;
    }

    copied.value = true;
    clearTimeout(copiedTimer);
    copiedTimer = setTimeout(() => (copied.value = false), 1500);
}

onBeforeUnmount(() => clearTimeout(copiedTimer));

const sourceLabel = computed(() => {
    if (props.message.source === "ai") return "IA";
    if (props.message.source === "bot") return "Chatbot";
    if (props.message.source === "system") return "Sistema";
    return props.message.user?.name;
});
</script>

<template>
    <div
        class="group flex items-center gap-1.5"
        :class="outbound ? 'justify-end' : 'justify-start'"
    >
        <div
            v-if="canCopy || canCancel || canDelete"
            class="flex shrink-0 items-center gap-0.5 opacity-0 transition-opacity focus-within:opacity-100 group-hover:opacity-100"
            :class="outbound ? '' : 'order-last'"
        >
            <button
                v-if="canCopy"
                type="button"
                class="rounded-control p-1 transition hover:bg-surface-hover"
                :class="
                    copied
                        ? 'text-success'
                        : 'text-content-subtle hover:text-content'
                "
                :title="copied ? 'Copiada' : 'Copiar Mensagem'"
                @click="copyBody"
            >
                <component :is="copied ? Check : Copy" :size="14" />
            </button>

            <button
                v-if="canCancel"
                type="button"
                class="rounded-control p-1 text-content-subtle transition hover:bg-surface-hover hover:text-content"
                title="Cancelar Envio"
                @click="emit('cancel', message)"
            >
                <Ban :size="14" />
            </button>

            <button
                v-if="canDelete"
                type="button"
                class="rounded-control p-1 text-content-subtle transition hover:bg-danger-soft hover:text-danger"
                title="Excluir Mensagem"
                @click="emit('delete', message)"
            >
                <Trash2 :size="14" />
            </button>
        </div>

        <div
            class="max-w-[75%] space-y-1.5 rounded-card border px-3 py-2"
            :class="tone"
        >
            <p
                v-if="whisper"
                class="flex items-center gap-1 text-[11px] font-medium text-warning"
                title="Visível apenas para a equipe. O contato não recebe esta mensagem."
            >
                <EyeOff :size="12" />
                Sussurro{{ sourceLabel ? ` · ${sourceLabel}` : "" }}
            </p>

            <p
                v-else-if="outbound && sourceLabel"
                class="text-[11px] font-medium text-content-muted"
            >
                {{ sourceLabel }}
            </p>

            <div
                v-if="quote"
                class="rounded-control border-l-2 border-primary bg-surface-muted px-2 py-1"
            >
                <p class="text-[11px] font-medium text-primary">
                    {{ quote.author }}
                </p>
                <p
                    class="line-clamp-3 text-xs text-content-muted [&_code]:font-mono"
                    v-html="quote.body"
                />
            </div>

            <MediaAttachment :message="message" />

            <p
                v-if="message.body"
                class="whitespace-pre-wrap break-words text-sm"
                v-html="body"
            />

            <p v-if="note" class="text-[11px] italic text-content-subtle">
                {{ note }}
            </p>

            <div
                class="flex items-center justify-end gap-1.5 text-[11px] text-content-subtle"
            >
                <button
                    v-if="outbound && stuck && !whisper"
                    type="button"
                    class="mr-auto flex items-center gap-1 rounded-control px-1.5 py-0.5 font-medium transition"
                    :class="
                        state === 'failed'
                            ? 'text-danger hover:bg-danger-soft'
                            : 'text-warning hover:bg-warning-soft'
                    "
                    @click="emit('retry', message)"
                >
                    <RotateCcw :size="11" />
                    Tentar de Novo
                </button>

                <span>{{
                    formatTime(message.sent_at ?? message.created_at)
                }}</span>

                <component
                    :is="statusIcon"
                    v-if="(outbound && !whisper) || deleted"
                    :size="12"
                    :title="statusLabel"
                    :class="{
                        'text-info': state === 'read',
                        'text-warning': state === 'stalled',
                        'text-danger': state === 'failed',
                    }"
                />
            </div>
        </div>
    </div>
</template>
