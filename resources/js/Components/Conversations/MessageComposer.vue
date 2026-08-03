<script setup>
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from "vue";
import { useHttp } from "@inertiajs/vue3";
import {
    Check,
    EyeOff,
    FileText,
    Image,
    Music,
    Paperclip,
    PenLine,
    Send,
    SlidersHorizontal,
    X,
} from "lucide-vue-next";
import Button from "../UI/Button.vue";
import Dropdown from "../UI/Dropdown.vue";
import DropdownItem from "../UI/DropdownItem.vue";
import EmojiPicker from "../UI/EmojiPicker.vue";
import InlineAlert from "../UI/InlineAlert.vue";

const props = defineProps({
    conversation: { type: Object, required: true },
    quickReplies: { type: Array, default: () => [] },
    disabled: { type: Boolean, default: false },
    signing: { type: Boolean, default: false },
});

const emit = defineEmits(["queued", "sent", "failed", "update:signing"]);

const form = useHttp({ body: "", media: null, internal: false, sign: false });
const typingRequest = useHttp({ typing: true });

const draft = ref("");
const whispering = ref(false);
const attachment = ref(null);
const accept = ref("");
const fileInput = ref(null);
const input = ref(null);
const showQuickReplies = ref(false);
const highlighted = ref(0);
const sendError = ref(null);

function focusInput() {
    if (props.disabled || !window.matchMedia("(min-width: 1024px)").matches) {
        return;
    }

    nextTick(() => input.value?.focus());
}

onMounted(focusInput);

const typingRefresh = 8000;
const typingIdle = 3000;

let announcedUntil = 0;
let typingTimeout = null;

function announceTyping(conversationId, typing) {
    typingRequest.typing = typing;
    typingRequest
        .post(`/atendimentos/${conversationId}/digitando`)
        .catch(() => {});
}

function keepTyping() {
    clearTimeout(typingTimeout);
    typingTimeout = setTimeout(stopTyping, typingIdle);

    if (Date.now() < announcedUntil) {
        return;
    }

    announcedUntil = Date.now() + typingRefresh;
    announceTyping(props.conversation.id, true);
}

function stopTyping(conversationId = props.conversation.id) {
    clearTimeout(typingTimeout);

    if (announcedUntil === 0) {
        return;
    }

    announcedUntil = 0;
    announceTyping(conversationId, false);
}

function forgetTyping() {
    clearTimeout(typingTimeout);
    announcedUntil = 0;
}

watch(
    () => props.conversation.id,
    (id, previous) => {
        stopTyping(previous);
        draft.value = "";
        whispering.value = false;
        clearFile();
        focusInput();
    },
);

function toggleWhisper() {
    whispering.value = !whispering.value;

    if (whispering.value) {
        stopTyping();
    }

    focusInput();
}

const composerOptions = computed(() => [
    {
        key: "whisper",
        label: "Sussurro",
        description: "Só a equipe vê, o contato não recebe.",
        icon: EyeOff,
        active: whispering.value,
        run: toggleWhisper,
    },
    {
        key: "signature",
        label: "Assinar Mensagens",
        description: "Seu nome na frente, só neste atendimento.",
        icon: PenLine,
        active: props.signing,
        run: () => emit("update:signing", !props.signing),
    },
]);

function insertEmoji(emoji) {
    const element = input.value;

    if (!element) {
        draft.value += emoji;

        return;
    }

    const start = element.selectionStart ?? draft.value.length;
    const end = element.selectionEnd ?? start;

    draft.value = draft.value.slice(0, start) + emoji + draft.value.slice(end);

    nextTick(() => {
        element.focus();
        element.setSelectionRange(start + emoji.length, start + emoji.length);
    });
}

onBeforeUnmount(() => stopTyping());

const connection = computed(() => props.conversation.connection ?? null);
const offline = computed(
    () => connection.value !== null && connection.value.status !== "connected",
);

const connectionWarning = computed(() => {
    if (!offline.value || whispering.value) {
        return null;
    }

    return connection.value.status === "connecting"
        ? `${connection.value.name} aguardando pareamento. Leia o QR Code para conectar.`
        : `${connection.value.name} desconectado. Novas mensagens não serão entregues até reconectar.`;
});

const failureReason = computed(
    () => connectionWarning.value ?? "Não foi possível enviar.",
);

const filteredReplies = computed(() => {
    if (!draft.value.startsWith("/")) {
        return [];
    }

    const term = draft.value.slice(1).toLowerCase();

    return props.quickReplies.filter(
        (reply) =>
            reply.shortcut.toLowerCase().includes(term) ||
            reply.title.toLowerCase().includes(term),
    );
});

watch(draft, (value) => {
    showQuickReplies.value =
        value.startsWith("/") && filteredReplies.value.length > 0;
    highlighted.value = 0;
});

function onInput(event) {
    if (whispering.value || event.target.value.trim() === "") {
        stopTyping();

        return;
    }

    keepTyping();
}

function moveHighlight(step) {
    const total = filteredReplies.value.length;

    if (total === 0) {
        return;
    }

    highlighted.value = (highlighted.value + step + total) % total;
}

function applyQuickReply(reply) {
    if (!reply) {
        return;
    }

    draft.value = reply.body.replaceAll(
        "{{contato.nome}}",
        props.conversation.contact?.name ?? "",
    );
    showQuickReplies.value = false;

    nextTick(() => {
        const element = input.value;

        if (!element) {
            return;
        }

        element.focus();
        element.setSelectionRange(element.value.length, element.value.length);
    });
}

function onKeydown(event) {
    if (showQuickReplies.value) {
        if (event.key === "ArrowDown") {
            event.preventDefault();
            moveHighlight(1);

            return;
        }

        if (event.key === "ArrowUp") {
            event.preventDefault();
            moveHighlight(-1);

            return;
        }

        if (event.key === "Enter" && !event.shiftKey) {
            event.preventDefault();
            applyQuickReply(filteredReplies.value[highlighted.value]);

            return;
        }

        if (event.key === "Escape") {
            event.preventDefault();
            showQuickReplies.value = false;

            return;
        }
    }

    if (event.key === "Enter" && !event.shiftKey) {
        event.preventDefault();
        submit();
    }
}

const attachmentKinds = [
    { label: "Fotos e Vídeos", accept: "image/*,video/*", icon: Image },
    { label: "Documento", accept: "", icon: FileText },
    { label: "Áudio", accept: "audio/*", icon: Music },
];

function pickAttachment(value) {
    accept.value = value;

    nextTick(() => fileInput.value?.click());
}

function pickFile(event) {
    attachment.value = event.target.files[0] ?? null;
}

function clearFile() {
    attachment.value = null;

    if (fileInput.value) {
        fileInput.value.value = "";
    }
}

function bubbleFor(body, media, internal) {
    return {
        id: `draft-${Date.now()}-${Math.random().toString(36).slice(2, 8)}`,
        conversation_id: props.conversation.id,
        direction: "outbound",
        type: media ? "document" : "text",
        status: "pending",
        source: "agent",
        is_internal: internal,
        body: body || null,
        media_url: null,
        media_name: media?.name ?? null,
        optimistic: true,
        queued_at: Date.now(),
        created_at: new Date().toISOString(),
        sent_at: new Date().toISOString(),
    };
}

async function submit() {
    const body = draft.value.trim();
    const media = attachment.value;
    const internal = whispering.value;

    if (props.disabled || (!body && !media)) {
        return;
    }

    const bubble = bubbleFor(body, media, internal);

    sendError.value = null;
    forgetTyping();
    emit("queued", bubble);

    form.body = body;
    form.media = media;
    form.internal = internal;
    form.sign = props.signing;

    draft.value = "";
    clearFile();
    showQuickReplies.value = false;
    focusInput();

    try {
        const response = await form.post(
            `/atendimentos/${props.conversation.id}/mensagens`,
        );

        if (response?.message) {
            emit("sent", { bubble, message: response.message });

            return;
        }

        sendError.value = form.errors.body ?? failureReason.value;
        emit("failed", { bubble, error: sendError.value });
    } catch (error) {
        emit("failed", { bubble, error: failureReason.value });
    }
}
</script>

<template>
    <div class="relative border-t border-border bg-surface p-3">
        <div
            v-if="showQuickReplies"
            class="absolute bottom-full left-3 mb-2 max-h-64 w-80 overflow-y-auto scrollbar-thin rounded-card border border-border-strong bg-surface p-1"
        >
            <button
                v-for="(reply, index) in filteredReplies"
                :key="reply.id"
                type="button"
                class="flex w-full flex-col items-start gap-0.5 rounded-control px-2.5 py-2 text-left transition"
                :class="
                    index === highlighted
                        ? 'bg-primary-soft'
                        : 'hover:bg-surface-hover'
                "
                @mouseenter="highlighted = index"
                @mousedown.prevent="applyQuickReply(reply)"
            >
                <span class="text-xs font-medium text-content"
                    >{{ reply.shortcut }} · {{ reply.title }}</span
                >
                <span class="line-clamp-2 text-xs text-content-muted">{{
                    reply.body
                }}</span>
            </button>

            <p class="px-2.5 py-1 text-[11px] text-content-subtle">
                Enter aplica · Setas navegam · Esc fecha
            </p>
        </div>

        <div
            v-if="
                $slots.notice || connectionWarning || whispering || attachment
            "
            class="mb-2 space-y-2"
        >
            <slot name="notice" />

            <InlineAlert v-if="whispering" level="warning">
                Sussurro: só a equipe vê esta mensagem dentro do sistema. O
                contato não recebe nada.

                <template #action>
                    <button
                        type="button"
                        class="shrink-0 font-medium underline-offset-2 hover:underline"
                        @click="toggleWhisper"
                    >
                        Sair
                    </button>
                </template>
            </InlineAlert>

            <InlineAlert v-if="connectionWarning" level="danger">{{
                connectionWarning
            }}</InlineAlert>

            <div
                v-if="attachment"
                class="flex items-center gap-2 rounded-control bg-surface-muted px-2.5 py-1.5 text-xs"
            >
                <Paperclip :size="14" />
                <span class="flex-1 truncate">{{ attachment.name }}</span>
                <button
                    type="button"
                    class="text-content-subtle hover:text-danger"
                    @click="clearFile"
                >
                    <X :size="14" />
                </button>
            </div>
        </div>

        <form
            class="flex items-center gap-1.5 sm:gap-2"
            @submit.prevent="submit"
        >
            <Dropdown align="left" width="w-56" direction="up">
                <template #trigger>
                    <button
                        type="button"
                        class="rounded-control p-2 text-content-muted transition hover:bg-surface-hover"
                        title="Anexar"
                        aria-label="Anexar arquivo"
                    >
                        <Paperclip :size="18" />
                    </button>
                </template>

                <DropdownItem
                    v-for="kind in attachmentKinds"
                    :key="kind.label"
                    :icon="kind.icon"
                    @click="pickAttachment(kind.accept)"
                >
                    {{ kind.label }}
                </DropdownItem>
            </Dropdown>

            <input
                ref="fileInput"
                type="file"
                class="hidden"
                :accept="accept"
                @change="pickFile"
            />

            <Dropdown align="left" width="w-64" direction="up">
                <template #trigger>
                    <button
                        type="button"
                        class="rounded-control p-2 transition"
                        :class="
                            whispering
                                ? 'bg-warning-soft text-warning'
                                : 'text-content-muted hover:bg-surface-hover'
                        "
                        title="Opções do Atendimento"
                        aria-label="Opções do atendimento"
                    >
                        <SlidersHorizontal :size="18" />
                    </button>
                </template>

                <DropdownItem
                    v-for="option in composerOptions"
                    :key="option.key"
                    :icon="option.icon"
                    :aria-pressed="option.active"
                    @click="option.run"
                >
                    <span class="flex min-w-0 flex-1 flex-col">
                        <span
                            class="truncate"
                            :class="
                                option.active ? 'font-medium text-primary' : ''
                            "
                        >
                            {{ option.label }}
                        </span>
                        <span class="truncate text-[11px] text-content-subtle">
                            {{ option.description }}
                        </span>
                    </span>

                    <span
                        class="flex h-4 w-4 shrink-0 items-center justify-center rounded border transition"
                        :class="
                            option.active
                                ? 'border-primary bg-primary text-primary-content'
                                : 'border-border'
                        "
                    >
                        <Check v-if="option.active" :size="11" />
                    </span>
                </DropdownItem>
            </Dropdown>

            <div class="relative flex min-w-0 flex-1 items-center">
                <textarea
                    ref="input"
                    v-model="draft"
                    rows="1"
                    :disabled="disabled"
                    :placeholder="Whispering ? 'Sussurrar...' : 'Mensagem'"
                    class="max-h-40 min-h-[2.5rem] w-full resize-y rounded-control border bg-surface py-2 pl-3 pr-10 text-sm text-content placeholder:text-content-subtle focus:outline-none disabled:opacity-60"
                    :class="
                        whispering
                            ? 'border-warning focus:border-warning'
                            : 'border-border focus:border-primary'
                    "
                    @keydown="onKeydown"
                    @input="onInput"
                />

                <div class="absolute right-1.5 top-1/2 -translate-y-1/2">
                    <EmojiPicker @pick="insertEmoji" />
                </div>
            </div>

            <Button
                type="submit"
                :icon="whispering ? EyeOff : Send"
                :variant="whispering ? 'secondary' : 'primary'"
                :disabled="disabled"
                :title="whispering ? 'Sussurrar' : 'Enviar'"
            >
                <span class="hidden sm:inline">{{
                    whispering ? "Sussurrar" : "Enviar"
                }}</span>
            </Button>
        </form>

        <p v-if="sendError" class="mt-1.5 text-xs text-danger">
            {{ sendError }}
        </p>
    </div>
</template>
