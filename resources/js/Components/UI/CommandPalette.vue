<script setup>
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from "vue";
import { router, useHttp } from "@inertiajs/vue3";
import {
    BookUser,
    ChartColumn,
    ClipboardList,
    LayoutDashboard,
    ListChecks,
    MessagesSquare,
    Plug,
    ScrollText,
    Search,
    Settings,
    ShieldCheck,
    Sparkles,
    Tag,
    UserPlus,
    Users,
    Workflow,
    Zap,
} from "lucide-vue-next";

const open = ref(false);
const term = ref("");
const results = ref({ conversations: [], contacts: [], modules: [] });
const highlighted = ref(0);
const searching = ref(false);
const input = ref(null);

const request = useHttp({});

const moduleIcons = {
    dashboard: LayoutDashboard,
    chat: MessagesSquare,
    users: Users,
    bolt: Zap,
    card: ClipboardList,
    queue: ListChecks,
    sparkles: Sparkles,
    flow: Workflow,
    plug: Plug,
    tag: Tag,
    "user-plus": UserPlus,
    shield: ShieldCheck,
    chart: ChartColumn,
    audit: ScrollText,
    settings: Settings,
};

const groups = computed(() =>
    [
        {
            label: "Atendimentos Ativos",
            icon: MessagesSquare,
            items: results.value.conversations,
        },
        { label: "Contatos", icon: BookUser, items: results.value.contacts },
        { label: "Módulos", icon: null, items: results.value.modules },
    ].filter((group) => group.items.length > 0),
);

const flat = computed(() => groups.value.flatMap((group) => group.items));

let searchTimeout = null;

async function load() {
    searching.value = true;

    try {
        const payload = await request.get(
            `/busca?busca=${encodeURIComponent(term.value)}`,
        );

        results.value = payload;
    } catch {
        results.value = { conversations: [], contacts: [], modules: [] };
    } finally {
        searching.value = false;
        highlighted.value = 0;
    }
}

watch(term, () => {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(load, 200);
});

function show() {
    open.value = true;
    term.value = "";
    highlighted.value = 0;
    load();

    nextTick(() => input.value?.focus());
}

function hide() {
    open.value = false;
    clearTimeout(searchTimeout);
}

function go(item) {
    if (!item) {
        return;
    }

    hide();
    router.visit(item.href);
}

function move(step) {
    const total = flat.value.length;

    if (total === 0) {
        return;
    }

    highlighted.value = (highlighted.value + step + total) % total;
}

function onKeydown(event) {
    const combo =
        (event.ctrlKey || event.metaKey) && event.key.toLowerCase() === "k";

    if (combo) {
        event.preventDefault();
        open.value ? hide() : show();

        return;
    }

    if (!open.value) {
        return;
    }

    if (event.key === "Escape") {
        event.preventDefault();
        event.stopPropagation();
        hide();

        return;
    }

    if (event.key === "ArrowDown") {
        event.preventDefault();
        move(1);

        return;
    }

    if (event.key === "ArrowUp") {
        event.preventDefault();
        move(-1);

        return;
    }

    if (event.key === "Enter") {
        event.preventDefault();
        go(flat.value[highlighted.value]);
    }
}

onMounted(() => document.addEventListener("keydown", onKeydown, true));

onBeforeUnmount(() => {
    document.removeEventListener("keydown", onKeydown, true);
    clearTimeout(searchTimeout);
});

defineExpose({ show });
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition-opacity duration-150 ease-out"
            enter-from-class="opacity-0 [&_[data-panel]]:-translate-y-2"
            leave-active-class="transition-opacity duration-100 ease-in"
            leave-to-class="opacity-0 [&_[data-panel]]:-translate-y-2"
        >
            <div
                v-if="open"
                data-command-palette
                class="fixed inset-0 z-[60] flex items-start justify-center p-4 pt-[12vh]"
            >
                <div
                    class="fixed inset-0 bg-overlay backdrop-blur-[2px]"
                    @click="hide"
                />

                <div
                    data-panel
                    class="relative z-10 w-full max-w-xl overflow-hidden rounded-card border border-border-strong bg-surface transition duration-150 ease-out"
                >
                    <div
                        class="flex items-center gap-2 border-b border-border px-3.5"
                    >
                        <Search
                            :size="16"
                            class="shrink-0 text-content-subtle"
                        />

                        <input
                            ref="input"
                            v-model="term"
                            type="text"
                            placeholder="Buscar atendimentos, contatos e módulos"
                            class="h-12 flex-1 bg-transparent text-sm text-content placeholder:text-content-subtle focus:outline-none"
                        />

                        <kbd
                            class="hidden shrink-0 rounded border border-border px-1.5 py-0.5 text-[10px] text-content-subtle sm:block"
                        >
                            Esc
                        </kbd>
                    </div>

                    <div
                        class="max-h-[60vh] overflow-y-auto scrollbar-thin p-1.5"
                    >
                        <template v-for="group in groups" :key="group.label">
                            <p
                                class="px-2 pb-1 pt-2 text-[10px] font-semibold uppercase tracking-wider text-content-subtle"
                            >
                                {{ group.label }}
                            </p>

                            <button
                                v-for="item in group.items"
                                :key="`${group.label}-${item.id}`"
                                type="button"
                                class="flex w-full items-center gap-2.5 rounded-control px-2.5 py-2 text-left transition"
                                :class="
                                    flat[highlighted] === item
                                        ? 'bg-primary-soft'
                                        : 'hover:bg-surface-hover'
                                "
                                @mouseenter="highlighted = flat.indexOf(item)"
                                @click="go(item)"
                            >
                                <component
                                    :is="
                                        group.icon ??
                                        moduleIcons[item.icon] ??
                                        LayoutDashboard
                                    "
                                    :size="15"
                                    class="shrink-0 text-content-muted"
                                />

                                <span
                                    class="min-w-0 flex-1 truncate text-sm text-content"
                                >
                                    {{ item.label }}
                                </span>

                                <span
                                    v-if="item.hint"
                                    class="shrink-0 truncate text-xs text-content-subtle"
                                >
                                    {{ item.hint }}
                                </span>
                            </button>
                        </template>

                        <p
                            v-if="!groups.length"
                            class="px-2.5 py-6 text-center text-xs text-content-subtle"
                        >
                            {{
                                searching
                                    ? "Buscando..."
                                    : "Nada encontrado para esta busca."
                            }}
                        </p>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
