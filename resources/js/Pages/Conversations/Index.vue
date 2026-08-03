<script setup>
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from "vue";
import {
    Head,
    InfiniteScroll,
    router,
    useHttp,
    usePage,
} from "@inertiajs/vue3";
import {
    ArrowDown,
    ArrowLeft,
    Check,
    EllipsisVertical,
    Hand,
    Inbox,
    Info,
    MessageSquarePlus,
    MessagesSquare,
    Plus,
    RefreshCw,
    Send,
    Smartphone,
    X,
} from "lucide-vue-next";
import {
    useEchoConnection,
    usePrivateChannel,
} from "../../Composables/useEchoChannel";
import { usePermissions } from "../../Composables/usePermissions";
import { formatPhone } from "../../Utils/format";
import ConversationSection from "../../Components/Conversations/ConversationSection.vue";
import ConversationDetails from "../../Components/Conversations/ConversationDetails.vue";
import ConversationContextMenu from "../../Components/Conversations/ConversationContextMenu.vue";
import ContactModal from "../../Components/Conversations/ContactModal.vue";
import TagsModal from "../../Components/Conversations/TagsModal.vue";
import TransferModal from "../../Components/Conversations/TransferModal.vue";
import NewConversationModal from "../../Components/Conversations/NewConversationModal.vue";
import MessageBubble from "../../Components/Conversations/MessageBubble.vue";
import ThreadMarker from "../../Components/Conversations/ThreadMarker.vue";
import MessageComposer from "../../Components/Conversations/MessageComposer.vue";
import SearchInput from "../../Components/UI/SearchInput.vue";
import EmptyState from "../../Components/UI/EmptyState.vue";
import Drawer from "../../Components/UI/Drawer.vue";
import ConfirmDialog from "../../Components/UI/ConfirmDialog.vue";
import InlineAlert from "../../Components/UI/InlineAlert.vue";
import Avatar from "../../Components/UI/Avatar.vue";
import Badge from "../../Components/UI/Badge.vue";
import Button from "../../Components/UI/Button.vue";
import Dropdown from "../../Components/UI/Dropdown.vue";
import DropdownItem from "../../Components/UI/DropdownItem.vue";

const props = defineProps({
    sections: { type: Array, default: () => [] },
    selected: { type: Object, default: null },
    messages: { type: Object, default: null },
    timeline: { type: Array, default: () => [] },
    filters: { type: Object, default: () => ({}) },
    queues: { type: Array, default: () => [] },
    agents: { type: Array, default: () => [] },
    flows: { type: Array, default: () => [] },
    connections: { type: Array, default: () => [] },
    signature: { type: Boolean, default: false },
    transfer_sections: { type: Array, default: () => [] },
    tags: { type: Array, default: () => [] },
    quick_replies: { type: Array, default: () => [] },
    visibility: {
        type: Object,
        default: () => ({ all: true, user_id: null, queue_ids: [] }),
    },
});

const page = usePage();
const { can } = usePermissions();

const messageRequest = useHttp({});
const draftRequest = useHttp({ body: "", internal: false, sign: false });

const partialProps = ["selected", "messages", "timeline", "sections"];

const search = ref(props.filters.search ?? "");
const sectionList = ref(props.sections);
const openSection = ref(
    props.selected?.section ??
        props.sections.find((section) => section.conversations.length > 0)
            ?.value ??
        null,
);
const thread = ref([]);
const threadOwner = ref(null);
const drafts = ref([]);
const messagesContainer = ref(null);
const unreadBelow = ref(false);
const detailsOpen = ref(false);
const signing = ref(props.signature);
const now = ref(Date.now());

const listNow = ref(Date.now());

const contextMenu = ref({ open: false, x: 0, y: 0, conversation: null });
const transfer = ref({
    open: false,
    conversation: null,
    section: "manual",
    assignee: null,
});
const tagging = ref({ open: false, conversation: null });
const editingContact = ref({ open: false, conversation: null });
const finishing = ref(null);
const deletingMessage = ref(null);
const interacting = ref(false);
const startingConversation = ref(false);
const taking = ref(null);

const tenantId = computed(() => page.props.tenant?.id);
const currentUserId = computed(() => page.props.auth?.user?.id ?? null);
const closed = computed(() => props.selected?.status === "closed");
const isGroup = computed(() => Boolean(props.selected?.is_group));

const headerActions = computed(() => {
    if (!props.selected) {
        return [];
    }

    const actions = [];

    if (can("conversations.assign") && !isGroup.value) {
        actions.push({
            label: "Transferir",
            icon: Send,
            run: () =>
                startTransfer(
                    props.selected,
                    props.selected.section === "manual" ? "manual" : "waiting",
                ),
        });
    }

    if (can("conversations.close") && !closed.value) {
        actions.push({
            label: "Finalizar",
            icon: Check,
            run: () => startFinish(props.selected),
        });
    }

    return actions;
});

const owner = computed(() => props.selected?.assigned_user ?? null);
const ownedByOther = computed(
    () => owner.value !== null && owner.value.id !== currentUserId.value,
);
const unassigned = computed(() => !isGroup.value && owner.value === null);
const locked = computed(() => ownedByOther.value && !interacting.value);

let searchTimeout = null;
let clock = null;
let listClock = null;
let readTimeout = null;
let pendingReadId = null;

const READ_RECEIPT_DELAY = 1000;

watch(
    () => props.sections,
    (sections) => (sectionList.value = sections),
);

watch(search, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        router.get(
            "/atendimentos",
            { search: search.value || undefined },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
                showProgress: false,
                only: ["sections", "filters"],
            },
        );
    }, 350);
});

function toggleSection(value) {
    openSection.value = openSection.value === value ? null : value;
}

function byRecency(conversations) {
    return [...conversations].sort((a, b) =>
        (b.last_message_at ?? "").localeCompare(a.last_message_at ?? ""),
    );
}

function isVisible(conversation) {
    if (props.visibility.all || conversation.is_group) {
        return true;
    }

    if (conversation.assigned_user?.id) {
        return conversation.assigned_user.id === props.visibility.user_id;
    }

    return props.visibility.queue_ids.includes(conversation.service_queue?.id);
}

function patchConversation(conversation, message = null) {
    if (!conversation?.id) {
        return;
    }

    const visible = isVisible(conversation);

    sectionList.value = sectionList.value.map((section) => {
        const previous =
            section.conversations.find((item) => item.id === conversation.id) ??
            null;
        const merged = {
            ...previous,
            ...conversation,
            ...(message
                ? {
                      last_message: message,
                      last_message_at:
                          message.sent_at ??
                          message.created_at ??
                          conversation.last_message_at,
                  }
                : {}),
        };

        const belongs =
            visible &&
            section.value === merged.section &&
            merged.status !== "closed";

        if (!belongs && previous === null) {
            return section;
        }

        return {
            ...section,
            total: Math.max(
                0,
                section.total + (belongs ? (previous ? 0 : 1) : -1),
            ),
            unread: Math.max(
                0,
                section.unread -
                    (previous?.unread_count ?? 0) +
                    (belongs ? merged.unread_count : 0),
            ),
            conversations: belongs
                ? byRecency([
                      merged,
                      ...section.conversations.filter(
                          (item) => item.id !== merged.id,
                      ),
                  ])
                : section.conversations.filter((item) => item.id !== merged.id),
        };
    });
}

function scrollToBottom() {
    if (messagesContainer.value) {
        messagesContainer.value.scrollTop =
            messagesContainer.value.scrollHeight;
    }

    unreadBelow.value = false;
}

function isAtBottom() {
    const element = messagesContainer.value;

    if (!element) {
        return true;
    }

    return element.scrollHeight - element.scrollTop - element.clientHeight < 80;
}

function jumpToLatest() {
    scrollToBottom();
    markAsRead(true);
}

function onThreadScroll() {
    if (!isAtBottom()) {
        return;
    }

    if (unreadBelow.value) {
        markAsRead(true);
    }

    unreadBelow.value = false;
}

function appendMessage(message) {
    if (thread.value.some((item) => item.id === message.id)) {
        return;
    }

    const following = isAtBottom();

    thread.value = [...thread.value, message];

    if (following || message.direction === "outbound") {
        nextTick(scrollToBottom);

        return;
    }

    unreadBelow.value = true;
}

/**
 * O servidor entrega o histórico em páginas e o infinite scroll vai juntando as
 * anteriores conforme o usuário sobe. Aqui elas se fundem com o que chegou pelo
 * tempo real: substituir a lista descartaria as mensagens recém-recebidas, e o
 * identificador é sequencial no tempo, então ordenar por ele basta. A fusão só
 * vale dentro do mesmo atendimento — ao trocar, a lista recomeça do zero.
 */
watch(
    [() => props.selected?.id, () => props.messages],
    ([conversationId, messages]) => {
        const switched = conversationId !== threadOwner.value;

        if (switched) {
            threadOwner.value = conversationId;
            thread.value = [];
            drafts.value = [];
        }

        const known = switched
            ? new Map()
            : new Map(thread.value.map((message) => [message.id, message]));

        for (const message of messages?.data ?? []) {
            known.set(message.id, { ...known.get(message.id), ...message });
        }

        const first = switched || thread.value.length === 0;

        thread.value = [...known.values()].sort((a, b) =>
            a.id.localeCompare(b.id),
        );

        /**
         * Ao subir, o próprio InfiniteScroll recoloca a leitura onde estava. Só
         * a abertura do atendimento precisa de empurrão, para nascer no fim.
         */
        if (first) {
            nextTick(scrollToBottom);
        }
    },
    { immediate: true },
);

/**
 * A linha do tempo vem inteira do servidor, mas o histórico chega por partes.
 * Enquanto houver páginas para trás, os marcos anteriores à mensagem mais
 * antiga na tela ficam de fora: eles pertencem a um trecho que ainda não foi
 * carregado e se amontoariam todos no topo.
 */
const wholeHistoryLoaded = computed(
    () => (page.scrollProps?.messages?.previousPage ?? null) === null,
);

const threadItems = computed(() => {
    const oldest = thread.value[0];
    const floor =
        wholeHistoryLoaded.value || oldest === undefined
            ? null
            : (oldest.sent_at ?? oldest.created_at);

    const events = props.timeline.filter(
        (event) => floor === null || event.at >= floor,
    );

    const items = [];
    let index = 0;

    const drain = (until) => {
        while (
            index < events.length &&
            (until === null || events[index].at <= until)
        ) {
            const event = events[index];

            items.push({ ...event, key: `${event.kind}-${event.at}` });
            index += 1;
        }
    };

    for (const message of thread.value) {
        drain(message.sent_at ?? message.created_at);
        items.push({ kind: "message", message, key: message.id });
    }

    drain(null);

    for (const draft of drafts.value) {
        items.push({ kind: "message", message: draft, key: draft.id });
    }

    return items;
});

function onQueued(draft) {
    drafts.value = [...drafts.value, draft];
    nextTick(scrollToBottom);
}

function onSent({ bubble, message }) {
    drafts.value = drafts.value.filter((item) => item.id !== bubble.id);
    appendMessage(message);
    patchConversation(props.selected, message);
}

function onFailed({ bubble, error }) {
    drafts.value = drafts.value.map((item) =>
        item.id === bubble.id ? { ...item, status: "failed", error } : item,
    );
}

function markMessage(id, changes) {
    const apply = (item) => (item.id === id ? { ...item, ...changes } : item);

    thread.value = thread.value.map(apply);
    drafts.value = drafts.value.map(apply);
}

async function cancelMessage(message) {
    markMessage(message.id, { status: "canceled", error: null });

    if (message.optimistic) {
        return;
    }

    await messageRequest.post(
        `/atendimentos/${props.selected.id}/mensagens/${message.id}/cancelar`,
    );
}

async function deleteMessage() {
    const message = deletingMessage.value;

    deletingMessage.value = null;
    markMessage(message.id, { status: "deleted", error: null });

    if (message.optimistic) {
        return;
    }

    await messageRequest.delete(
        `/atendimentos/${props.selected.id}/mensagens/${message.id}`,
    );

    reloadSections();
}

async function retryMessage(message) {
    if (message.optimistic) {
        drafts.value = drafts.value.filter((item) => item.id !== message.id);
        draftRequest.body = message.body;
        draftRequest.internal = Boolean(message.is_internal);
        draftRequest.sign = signing.value;

        try {
            const payload = await draftRequest.post(
                `/atendimentos/${props.selected.id}/mensagens`,
            );

            appendMessage(payload.message);
        } catch {
            drafts.value = [
                ...drafts.value,
                { ...message, status: "failed", queued_at: Date.now() },
            ];
        }

        return;
    }

    thread.value = thread.value.map((item) =>
        item.id === message.id
            ? {
                  ...item,
                  status: "pending",
                  error: null,
                  sent_at: new Date().toISOString(),
              }
            : item,
    );

    await messageRequest.post(
        `/atendimentos/${props.selected.id}/mensagens/${message.id}/reenviar`,
    );
}

function markAsRead(force = false) {
    if (!props.selected || (!force && props.selected.unread_count === 0)) {
        return;
    }

    patchConversation({ ...props.selected, unread_count: 0 });

    if (pendingReadId && pendingReadId !== props.selected.id) {
        flushReadReceipt();
    }

    pendingReadId = props.selected.id;

    clearTimeout(readTimeout);
    readTimeout = setTimeout(flushReadReceipt, READ_RECEIPT_DELAY);
}

function flushReadReceipt() {
    clearTimeout(readTimeout);
    readTimeout = null;

    const conversationId = pendingReadId;
    pendingReadId = null;

    if (!conversationId) {
        return;
    }

    router.post(
        `/atendimentos/${conversationId}/lida`,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            showProgress: false,
            async: true,
            only: ["sections", "selected"],
        },
    );
}

watch(
    () => props.selected?.id,
    () => {
        markAsRead();
        detailsOpen.value = false;
        interacting.value = false;
        signing.value = props.signature;
        closeContextMenu();

        if (props.selected) {
            openSection.value = props.selected.section;
        }
    },
    { immediate: true },
);

function openConversation(conversation) {
    router.visit(`/atendimentos/${conversation.id}`, {
        only: partialProps,
        reset: ["messages"],
        preserveState: true,
        preserveScroll: true,
        showProgress: false,
    });
}

function startTake(conversation) {
    closeContextMenu();

    if (conversation.section === "automatic") {
        startTransfer(conversation, "manual", currentUserId.value);

        return;
    }

    taking.value = conversation;
}

function takeConversation() {
    const conversation = taking.value;

    router.post(
        `/atendimentos/${conversation.id}/assumir`,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            only: [...partialProps, "flash"],
            onSuccess: () => openConversation(conversation),
            onFinish: () => (taking.value = null),
        },
    );
}

/**
 * Quem transfere para si mesmo quer continuar o atendimento, então a lista abre
 * no Manual e a conversa vai junto. Transferir para outra pessoa só atualiza.
 */
function onTransferred({ section, userId }) {
    openSection.value = section;

    if (section === "manual" && userId === currentUserId.value) {
        openConversation(transfer.value.conversation);

        return;
    }

    reloadSelected();
}

function reloadSections() {
    router.reload({
        only: ["sections"],
        preserveScroll: true,
        preserveState: true,
        showProgress: false,
        async: true,
    });
}

function reloadSelected() {
    router.reload({
        only: partialProps,
        preserveScroll: true,
        preserveState: true,
        showProgress: false,
    });
}

function refreshSelectedHeader() {
    router.reload({
        only: ["selected", "timeline"],
        preserveScroll: true,
        preserveState: true,
        showProgress: false,
        async: true,
    });
}

function minimise() {
    interacting.value = false;

    router.visit("/atendimentos", {
        only: partialProps,
        reset: ["messages"],
        preserveState: true,
        preserveScroll: true,
        showProgress: false,
    });
}

function openContextMenu({ conversation, event }) {
    contextMenu.value = {
        open: true,
        x: event.clientX,
        y: event.clientY,
        conversation,
    };
}

function closeContextMenu() {
    contextMenu.value = { ...contextMenu.value, open: false };
}

function startTransfer(conversation, section = "manual", assignee = null) {
    closeContextMenu();
    transfer.value = { open: true, conversation, section, assignee };
}

function startTagging(conversation) {
    closeContextMenu();
    tagging.value = { open: true, conversation };
}

function startContactEdit(conversation) {
    closeContextMenu();
    editingContact.value = { open: true, conversation };
}

function startFinish(conversation) {
    closeContextMenu();
    finishing.value = conversation;
}

function finishConversation() {
    const conversation = finishing.value;

    router.post(
        `/atendimentos/${conversation.id}/encerrar`,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            only: [...partialProps, "flash"],
            onSuccess: () => {
                finishing.value = null;

                if (props.selected?.id === conversation.id) {
                    minimise();
                }
            },
            onError: () => (finishing.value = null),
        },
    );
}

function reopenConversation() {
    router.post(
        `/atendimentos/${props.selected.id}/reabrir`,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            only: [...partialProps, "flash"],
        },
    );
}

function onEscape(event) {
    if (event.key !== "Escape" || !props.selected) {
        return;
    }

    const blocked =
        contextMenu.value.open ||
        detailsOpen.value ||
        transfer.value.open ||
        tagging.value.open ||
        editingContact.value.open ||
        startingConversation.value ||
        finishing.value !== null ||
        taking.value !== null ||
        deletingMessage.value !== null ||
        document.querySelector("[data-command-palette]") !== null;

    if (blocked) {
        return;
    }

    event.preventDefault();
    minimise();
}

onMounted(() => {
    clock = setInterval(() => (now.value = Date.now()), 2000);
    listClock = setInterval(() => (listNow.value = Date.now()), 30000);
    document.addEventListener("keydown", onEscape);
});

onBeforeUnmount(() => {
    clearInterval(clock);
    clearInterval(listClock);
    clearTimeout(searchTimeout);
    document.removeEventListener("keydown", onEscape);
    flushReadReceipt();
});

const { online } = useEchoConnection(() => {
    reloadSections();

    if (props.selected) {
        reloadSelected();
    }
});

usePrivateChannel(
    () => (tenantId.value ? `tenants.${tenantId.value}.conversations` : null),
    {
        "message.received": (event) =>
            patchConversation(event.conversation, event.message),
        "message.sent": (event) =>
            patchConversation(event.conversation, event.message),
        "conversation.updated": (event) => {
            patchConversation(event.conversation);

            if (event.conversation?.id === props.selected?.id) {
                refreshSelectedHeader();
            }
        },
    },
);

usePrivateChannel(
    () => (props.selected ? `conversations.${props.selected.id}` : null),
    {
        "message.received": (event) => {
            const reading = isAtBottom();

            appendMessage(event.message);

            if (reading) {
                markAsRead(true);
            }
        },
        "message.sent": (event) => appendMessage(event.message),
        "message.status": (event) => {
            thread.value = thread.value.map((message) =>
                message.id === event.id ? { ...message, ...event } : message,
            );
        },
    },
);
</script>

<template>
    <Head title="Atendimentos" />

    <div class="flex h-full min-h-0">
        <section
            class="w-full shrink-0 flex-col border-r border-border bg-surface lg:flex lg:w-80"
            :class="selected ? 'hidden' : 'flex'"
        >
            <div
                class="flex h-15 shrink-0 items-center gap-2 border-b border-border px-3"
            >
                <SearchInput
                    v-model="search"
                    placeholder="Buscar contato"
                    class="flex-1"
                />

                <button
                    v-if="can('conversations.reply')"
                    type="button"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-control bg-primary text-primary-content transition hover:opacity-90"
                    title="Novo Atendimento"
                    @click="startingConversation = true"
                >
                    <Plus :size="18" />
                </button>
            </div>

            <p
                v-if="!online"
                class="flex shrink-0 items-center gap-1.5 border-b border-border bg-warning-soft px-3 py-1.5 text-xs text-warning"
            >
                <RefreshCw :size="13" class="animate-spin" />
                Reconectando. As novidades aparecem assim que a conexão voltar.
            </p>

            <div class="flex-1 overflow-y-auto scrollbar-thin">
                <ConversationSection
                    v-for="section in sectionList"
                    :key="section.value"
                    :section="section"
                    :selected-id="selected?.id ?? null"
                    :collapsed="openSection !== section.value"
                    :now="listNow"
                    @toggle="toggleSection"
                    @context="openContextMenu"
                    @take="startTake"
                />

                <EmptyState
                    v-if="!sectionList.length"
                    :icon="Inbox"
                    title="Nenhum Atendimento"
                    description="Aguarde novas mensagens ou ajuste a busca."
                />
            </div>
        </section>

        <section v-if="selected" class="flex min-w-0 flex-1 flex-col bg-canvas">
            <header
                class="flex h-15 shrink-0 items-center gap-2 border-b border-border bg-surface px-3 sm:gap-3 sm:px-4"
            >
                <button
                    type="button"
                    class="rounded-control p-1.5 text-content-muted transition hover:bg-surface-hover lg:hidden"
                    aria-label="Voltar para a lista"
                    @click="minimise"
                >
                    <ArrowLeft :size="18" />
                </button>

                <button
                    type="button"
                    class="flex min-w-0 flex-1 items-center gap-2 rounded-control px-1 py-1 text-left transition hover:bg-surface-hover sm:gap-3"
                    aria-label="Ver informações do contato"
                    title="Ver Informações"
                    @click="detailsOpen = true"
                >
                    <Avatar
                        :name="selected.contact?.name"
                        :src="selected.contact?.avatar_url"
                        :size="36"
                    />

                    <span class="min-w-0 flex-1">
                        <span
                            class="block truncate text-sm font-semibold text-content"
                        >
                            {{ selected.contact?.name }}
                        </span>
                        <span class="block truncate text-xs text-content-muted">
                            {{
                                formatPhone(
                                    selected.contact_channel?.identifier,
                                )
                            }}
                        </span>
                    </span>
                </button>

                <span
                    v-if="selected.connection"
                    class="hidden items-center gap-1.5 rounded-control border border-border px-2 py-1 text-xs text-content-muted xl:inline-flex"
                    :title="`Atendimento pelo canal ${selected.connection.name} · ${selected.connection.status_label}`"
                >
                    <Smartphone :size="13" class="shrink-0" />
                    <span class="max-w-[9rem] truncate">{{
                        selected.connection.name
                    }}</span>
                    <span
                        class="h-1.5 w-1.5 shrink-0 rounded-full"
                        :class="
                            selected.connection.status === 'connected'
                                ? 'bg-success'
                                : 'bg-warning'
                        "
                    />
                </span>

                <Badge
                    :color="selected.status_color"
                    class="hidden lg:inline-flex"
                    >{{ selected.section_label }}</Badge
                >
                <Badge
                    v-if="selected.assigned_user"
                    color="muted"
                    class="hidden xl:inline-flex"
                >
                    {{ selected.assigned_user.name }}
                </Badge>

                <Dropdown
                    v-if="headerActions.length"
                    align="right"
                    class="shrink-0 lg:hidden"
                >
                    <template #trigger>
                        <button
                            type="button"
                            class="rounded-control p-1.5 text-content-muted transition hover:bg-surface-hover hover:text-content"
                            aria-label="Ações do atendimento"
                        >
                            <EllipsisVertical :size="18" />
                        </button>
                    </template>

                    <DropdownItem
                        v-for="action in headerActions"
                        :key="action.label"
                        :icon="action.icon"
                        @click="action.run"
                    >
                        {{ action.label }}
                    </DropdownItem>
                </Dropdown>

                <div class="hidden shrink-0 items-center gap-1 lg:flex">
                    <Button
                        v-for="action in headerActions"
                        :key="`bar-${action.label}`"
                        size="sm"
                        variant="ghost"
                        :icon="action.icon"
                        @click="action.run"
                    >
                        {{ action.label }}
                    </Button>

                    <Button
                        size="sm"
                        variant="ghost"
                        :icon="Info"
                        aria-label="Ver detalhes do atendimento"
                        title="Detalhes"
                        @click="detailsOpen = true"
                    />

                    <Button
                        size="sm"
                        variant="ghost"
                        :icon="X"
                        aria-label="Fechar este atendimento na tela"
                        title="Fechar na Tela (Esc)"
                        @click="minimise"
                    />
                </div>
            </header>

            <div class="relative flex min-h-0 flex-1 flex-col">
                <div
                    ref="messagesContainer"
                    class="flex-1 space-y-2 overflow-y-auto scrollbar-thin px-3 py-4 sm:px-4"
                    @scroll.passive="onThreadScroll"
                >
                    <InfiniteScroll
                        :key="selected.id"
                        data="messages"
                        :buffer="600"
                        only-previous
                        preserve-url
                        class="space-y-2"
                    >
                        <template #previous="{ loading }">
                            <p
                                v-if="loading"
                                class="py-2 text-center text-xs text-content-subtle"
                            >
                                Carregando o histórico...
                            </p>
                        </template>

                        <template v-for="item in threadItems" :key="item.key">
                            <ThreadMarker
                                v-if="item.kind !== 'message'"
                                :kind="item.kind"
                                :label="item.label"
                                :at="item.at"
                            />
                            <MessageBubble
                                v-else
                                :message="item.message"
                                :now="now"
                                :manageable="can('conversations.messages.manage')"
                                :contact-name="selected.contact?.name"
                                @retry="retryMessage"
                                @cancel="cancelMessage"
                                @delete="deletingMessage = $event"
                            />
                        </template>
                    </InfiniteScroll>

                    <EmptyState
                        v-if="threadItems.length === 0"
                        :icon="MessagesSquare"
                        title="Sem Mensagens"
                        description="Envie a primeira mensagem para iniciar a conversa."
                    />
                </div>

                <button
                    v-if="unreadBelow"
                    type="button"
                    class="absolute bottom-3 left-1/2 flex -translate-x-1/2 items-center gap-1.5 rounded-full border border-border-strong bg-surface px-3 py-1.5 text-xs font-medium text-content transition hover:bg-surface-hover"
                    @click="jumpToLatest"
                >
                    <ArrowDown :size="14" />
                    Mensagens Novas
                </button>
            </div>

            <div
                v-if="closed"
                class="flex items-center justify-between gap-3 border-t border-border bg-surface px-4 py-3"
            >
                <p class="text-xs text-content-muted">
                    Este atendimento está encerrado.
                </p>

                <Button
                    v-if="can('conversations.close')"
                    size="sm"
                    variant="secondary"
                    :icon="RefreshCw"
                    @click="reopenConversation"
                >
                    Reabrir
                </Button>
            </div>

            <div
                v-else-if="unassigned"
                class="flex flex-wrap items-center justify-between gap-3 border-t border-border bg-surface px-4 py-3"
            >
                <p class="text-xs text-content-muted">
                    Ninguém assumiu este atendimento. Assuma para responder pelo
                    Manual.
                </p>

                <Button
                    v-if="can('conversations.reply')"
                    size="sm"
                    :icon="Hand"
                    @click="startTake(selected)"
                >
                    Assumir
                </Button>
            </div>

            <div
                v-else-if="locked"
                class="flex flex-wrap items-center justify-between gap-3 border-t border-border bg-surface px-4 py-3"
            >
                <p class="text-xs text-content-muted">
                    Em atendimento por
                    <span class="font-medium text-content">{{
                        owner.name
                    }}</span
                    >.
                </p>

                <div class="flex items-center gap-2">
                    <Button
                        size="sm"
                        variant="secondary"
                        :icon="MessageSquarePlus"
                        @click="interacting = true"
                    >
                        Interagir
                    </Button>

                    <Button
                        v-if="can('conversations.reply')"
                        size="sm"
                        :icon="Hand"
                        @click="startTake(selected)"
                    >
                        Assumir
                    </Button>
                </div>
            </div>

            <MessageComposer
                v-else
                :conversation="selected"
                v-model:signing="signing"
                :quick-replies="quick_replies"
                @queued="onQueued"
                @sent="onSent"
                @failed="onFailed"
            >
                <template v-if="ownedByOther" #notice>
                    <InlineAlert>
                        Interagindo no atendimento de {{ owner.name }} sem
                        assumir.

                        <template #action>
                            <button
                                type="button"
                                class="shrink-0 font-medium underline-offset-2 hover:underline"
                                @click="interacting = false"
                            >
                                Sair
                            </button>
                        </template>
                    </InlineAlert>
                </template>
            </MessageComposer>
        </section>

        <section
            v-else
            class="hidden flex-1 items-center justify-center bg-canvas lg:flex"
        >
            <EmptyState
                :icon="MessagesSquare"
                title="Selecione um Atendimento"
                description="Escolha uma conversa na lista para começar a responder."
            />
        </section>
    </div>

    <Drawer
        :open="detailsOpen && selected !== null"
        title="Detalhes do Atendimento"
        :description="selected?.contact?.name"
        size="md"
        @close="detailsOpen = false"
    >
        <ConversationDetails
            v-if="selected"
            v-model:signing="signing"
            :conversation="selected"
            :tags="tags"
            @transfer="
                startTransfer(
                    selected,
                    selected.section === 'manual' ? 'manual' : 'waiting',
                )
            "
            @finish="startFinish(selected)"
            @reopen="reopenConversation"
            @edit-contact="startContactEdit(selected)"
        />
    </Drawer>

    <ConversationContextMenu
        :open="contextMenu.open"
        :x="contextMenu.x"
        :y="contextMenu.y"
        :conversation="contextMenu.conversation"
        :can-transfer="can('conversations.assign')"
        :can-take="can('conversations.reply')"
        :can-close="can('conversations.close')"
        :can-tag="can('conversations.tags')"
        :can-edit-contact="can('contacts.update')"
        @close="closeContextMenu"
        @transfer="startTransfer(contextMenu.conversation)"
        @take="startTake(contextMenu.conversation)"
        @tags="startTagging(contextMenu.conversation)"
        @edit-contact="startContactEdit(contextMenu.conversation)"
        @finish="startFinish(contextMenu.conversation)"
    />

    <NewConversationModal
        :open="startingConversation"
        :connections="connections"
        @close="startingConversation = false"
    />

    <TransferModal
        :open="transfer.open"
        :conversation="transfer.conversation"
        :sections="transfer_sections"
        :queues="queues"
        :agents="agents"
        :flows="flows"
        :section="transfer.section"
        :assignee="transfer.assignee"
        @close="transfer = { ...transfer, open: false }"
        @transferred="onTransferred"
    />

    <TagsModal
        :open="tagging.open"
        :conversation="tagging.conversation"
        :tags="tags"
        @close="tagging = { ...tagging, open: false }"
        @saved="reloadSelected"
    />

    <ContactModal
        :open="editingContact.open"
        :contact="editingContact.conversation?.contact ?? null"
        :tags="tags"
        @close="editingContact = { ...editingContact, open: false }"
        @saved="reloadSelected"
    />

    <ConfirmDialog
        :open="taking !== null"
        title="Assumir Atendimento"
        :message="
            taking?.assigned_user
                ? `O atendimento de ${taking?.contact?.name} passa de ${taking?.assigned_user?.name} para você.`
                : `O atendimento de ${taking?.contact?.name} passa para você no setor ${taking?.service_queue?.name ?? 'padrão'}.`
        "
        confirm-label="Assumir"
        @close="taking = null"
        @confirm="takeConversation"
    />

    <ConfirmDialog
        :open="deletingMessage !== null"
        title="Excluir Mensagem"
        message="A mensagem sai do histórico do atendimento e é apagada também no WhatsApp do contato."
        confirm-label="Excluir"
        @close="deletingMessage = null"
        @confirm="deleteMessage"
    />

    <ConfirmDialog
        :open="finishing !== null"
        title="Finalizar Atendimento"
        :message="`O atendimento de ${finishing?.contact?.name} será encerrado. O contato pode reabrir enviando uma nova mensagem.`"
        confirm-label="Finalizar"
        @close="finishing = null"
        @confirm="finishConversation"
    />
</template>
