<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import { storeToRefs } from "pinia";
import { useUiStore } from "../Stores/ui";
import {
    Building2,
    ChartColumn,
    ClipboardList,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    LayoutDashboard,
    ListChecks,
    Lock,
    LogOut,
    Menu,
    MessagesSquare,
    Moon,
    Plug,
    ScrollText,
    Search,
    Settings,
    ShieldCheck,
    Sparkles,
    Sun,
    Tag,
    UserPlus,
    Users,
    Volume2,
    VolumeX,
    Workflow,
    Zap,
} from "lucide-vue-next";
import { useConversationAlerts } from "../Composables/useConversationAlerts";
import { unlockAlerts } from "../Services/sound";
import { usePrivateChannel } from "../Composables/useEchoChannel";
import Avatar from "../Components/UI/Avatar.vue";
import CommandPalette from "../Components/UI/CommandPalette.vue";
import Dropdown from "../Components/UI/Dropdown.vue";
import DropdownItem from "../Components/UI/DropdownItem.vue";
import NotificationBell from "../Components/UI/NotificationBell.vue";
import SidebarLink from "../Components/UI/SidebarLink.vue";
import ToastHost from "../Components/UI/ToastHost.vue";

const page = usePage();
const ui = useUiStore();
const { sidebarCollapsed, mobileSidebarOpen } = storeToRefs(ui);

const palette = ref(null);

const user = computed(() => page.props.auth?.user);
const tenant = computed(() => page.props.tenant);
const navigation = computed(() => page.props.navigation ?? []);
const alerts = computed(() => page.props.alerts ?? []);
const currentPath = computed(() => page.url.split("?")[0]);
const isSuperAdmin = computed(() => Boolean(page.props.auth?.is_super_admin));
const canSwitchTenant = computed(() => Boolean(page.props.can_switch_tenant));

const menuIcons = {
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

function isActive(href) {
    return (
        currentPath.value === href || currentPath.value.startsWith(`${href}/`)
    );
}

function toggleSidebar() {
    sidebarCollapsed.value = !sidebarCollapsed.value;
}

function logout() {
    router.post("/sair");
}

function lockScreen() {
    router.post("/bloqueio");
}

let idleTimer = null;

function resetIdleTimer() {
    const minutes = page.props.auth?.auto_lock_minutes;

    clearTimeout(idleTimer);

    if (!minutes) {
        return;
    }

    idleTimer = setTimeout(lockScreen, minutes * 60 * 1000);
}

const idleEvents = ["mousemove", "keydown", "click", "scroll"];

const gestureEvents = ["pointerdown", "keydown"];

onMounted(() => {
    ui.applyTheme();
    idleEvents.forEach((event) =>
        window.addEventListener(event, resetIdleTimer, { passive: true }),
    );
    gestureEvents.forEach((event) =>
        window.addEventListener(event, unlockAlerts, {
            once: true,
            passive: true,
        }),
    );
    resetIdleTimer();
});

onBeforeUnmount(() => {
    idleEvents.forEach((event) =>
        window.removeEventListener(event, resetIdleTimer),
    );
    gestureEvents.forEach((event) =>
        window.removeEventListener(event, unlockAlerts),
    );
    clearTimeout(idleTimer);
});

usePrivateChannel(
    () => (tenant.value ? `tenants.${tenant.value.id}.connections` : null),
    {
        "connector.status": () =>
            router.reload({
                only: ["alerts"],
                preserveScroll: true,
                preserveState: true,
            }),
    },
);

useConversationAlerts();
</script>

<template>
    <div class="flex h-screen overflow-hidden bg-canvas">
        <Transition
            enter-active-class="transition duration-200"
            enter-from-class="opacity-0"
            leave-active-class="transition duration-150"
            leave-to-class="opacity-0"
        >
            <div
                v-if="mobileSidebarOpen"
                class="fixed inset-0 z-30 bg-overlay lg:hidden"
                @click="mobileSidebarOpen = false"
            />
        </Transition>

        <aside
            class="fixed inset-y-0 left-0 z-40 flex flex-col border-r border-border bg-surface transition-all duration-200 lg:static"
            :class="[
                sidebarCollapsed ? 'w-[64px]' : 'w-60',
                mobileSidebarOpen
                    ? 'translate-x-0'
                    : '-translate-x-full lg:translate-x-0',
            ]"
        >
            <div
                class="flex h-14 shrink-0 items-center border-b border-border"
                :class="
                    sidebarCollapsed ? 'justify-center px-0' : 'gap-2.5 px-4'
                "
            >
                <span
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-control bg-primary text-primary-content"
                >
                    <MessagesSquare :size="18" />
                </span>

                <div v-if="!sidebarCollapsed" class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-content">
                        {{ page.props.app.name }}
                    </p>
                    <p
                        v-if="tenant"
                        class="truncate text-xs text-content-muted"
                    >
                        {{ tenant.name }}
                    </p>
                </div>
            </div>

            <nav
                class="flex-1 overflow-y-auto scrollbar-thin py-3"
                :class="
                    sidebarCollapsed
                        ? 'flex flex-col items-center gap-1 px-0'
                        : 'space-y-5 px-2.5'
                "
            >
                <template v-if="sidebarCollapsed">
                    <SidebarLink
                        v-for="item in navigation.flatMap(
                            (section) => section.items,
                        )"
                        :key="item.href"
                        :href="item.href"
                        :label="item.label"
                        :icon="menuIcons[item.icon] ?? LayoutDashboard"
                        :active="isActive(item.href)"
                        :prefetch="
                            item.href === '/atendimentos'
                                ? ['mount', 'hover']
                                : 'hover'
                        "
                        collapsed
                    />
                </template>

                <div
                    v-for="section in navigation"
                    v-else
                    :key="section.label"
                    class="space-y-1"
                >
                    <p
                        class="px-2 text-[10px] font-semibold uppercase tracking-wider text-content-subtle"
                    >
                        {{ section.label }}
                    </p>

                    <SidebarLink
                        v-for="item in section.items"
                        :key="item.href"
                        :href="item.href"
                        :label="item.label"
                        :icon="menuIcons[item.icon] ?? LayoutDashboard"
                        :active="isActive(item.href)"
                        :prefetch="
                            item.href === '/atendimentos'
                                ? ['mount', 'hover']
                                : 'hover'
                        "
                    />
                </div>
            </nav>

            <button
                type="button"
                class="hidden h-11 shrink-0 items-center border-t border-border text-xs text-content-subtle transition hover:text-content lg:flex"
                :class="
                    sidebarCollapsed ? 'justify-center px-0' : 'gap-2.5 px-4'
                "
                :title="sidebarCollapsed ? 'Expandir Menu' : 'Recolher Menu'"
                @click="toggleSidebar"
            >
                <component
                    :is="sidebarCollapsed ? ChevronRight : ChevronLeft"
                    :size="16"
                />
                <span v-if="!sidebarCollapsed">Recolher Menu</span>
            </button>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            <header
                class="flex h-14 shrink-0 items-center justify-between gap-3 border-b border-border bg-surface px-4"
            >
                <button
                    type="button"
                    class="rounded-control p-1.5 text-content-muted transition hover:bg-surface-hover lg:hidden"
                    @click="mobileSidebarOpen = true"
                >
                    <Menu />
                </button>

                <div class="flex min-w-0 flex-1 items-center gap-2">
                    <button
                        type="button"
                        class="flex h-9 items-center gap-2 rounded-control border border-border px-2.5 text-xs text-content-subtle transition hover:bg-surface-hover hover:text-content"
                        title="Buscar (Ctrl + K)"
                        @click="palette?.show()"
                    >
                        <Search :size="15" class="shrink-0" />
                        <span class="hidden sm:block">Buscar</span>
                        <kbd
                            class="hidden rounded border border-border px-1 py-0.5 text-[10px] md:block"
                        >
                            Ctrl + K
                        </kbd>
                    </button>

                    <span
                        v-if="tenant?.is_workspace"
                        class="flex items-center gap-1.5 rounded-control border border-primary px-2 py-1.5 text-xs text-primary"
                    >
                        <Building2 :size="14" class="shrink-0" />
                        <span class="max-w-[10rem] truncate">{{
                            tenant.name
                        }}</span>
                    </span>

                    <slot name="topbar" />
                </div>

                <div class="flex items-center gap-1.5">
                    <Link
                        v-if="isSuperAdmin"
                        href="/admin/tenants"
                        class="hidden items-center gap-1.5 rounded-control border border-border px-2 py-1.5 text-xs text-content-muted transition hover:bg-surface-hover hover:text-content sm:flex"
                    >
                        <ShieldCheck :size="14" />
                        Administração
                    </Link>

                    <NotificationBell :alerts="alerts" />

                    <button
                        type="button"
                        class="rounded-control p-1.5 transition hover:bg-surface-hover hover:text-content"
                        :class="
                            ui.soundAlerts
                                ? 'text-content-muted'
                                : 'text-content-subtle'
                        "
                        :title="
                            ui.soundAlerts
                                ? 'Silenciar Avisos'
                                : 'Ativar Avisos Sonoros'
                        "
                        @click="ui.toggleSoundAlerts()"
                    >
                        <component
                            :is="ui.soundAlerts ? Volume2 : VolumeX"
                            :size="18"
                        />
                    </button>

                    <button
                        type="button"
                        class="rounded-control p-1.5 text-content-muted transition hover:bg-surface-hover hover:text-content"
                        title="Alternar Tema"
                        @click="ui.toggleTheme()"
                    >
                        <component :is="ui.isDark ? Sun : Moon" :size="18" />
                    </button>

                    <button
                        type="button"
                        class="rounded-control p-1.5 text-content-muted transition hover:bg-surface-hover hover:text-content"
                        title="Bloquear Tela"
                        @click="lockScreen"
                    >
                        <Lock :size="18" />
                    </button>

                    <Dropdown>
                        <template #trigger>
                            <button
                                type="button"
                                class="flex items-center gap-2 rounded-control p-1 pr-2 transition hover:bg-surface-hover"
                            >
                                <Avatar
                                    :name="user?.name"
                                    :src="user?.avatar_url"
                                    :size="28"
                                />
                                <span
                                    class="hidden text-sm text-content sm:block"
                                    >{{ user?.name }}</span
                                >
                                <ChevronDown
                                    :size="14"
                                    class="text-content-subtle"
                                />
                            </button>
                        </template>

                        <DropdownItem :icon="Users" href="/configuracoes"
                            >Minha Conta</DropdownItem
                        >
                        <DropdownItem
                            v-if="canSwitchTenant"
                            :icon="Building2"
                            href="/selecionar-conta"
                            >Trocar de Conta</DropdownItem
                        >
                        <DropdownItem
                            v-if="isSuperAdmin"
                            :icon="ShieldCheck"
                            href="/admin/tenants"
                            >Administração</DropdownItem
                        >
                        <DropdownItem :icon="Lock" @click="lockScreen"
                            >Bloquear Tela</DropdownItem
                        >
                        <DropdownItem :icon="LogOut" danger @click="logout"
                            >Sair</DropdownItem
                        >
                    </Dropdown>
                </div>
            </header>

            <main class="flex-1 overflow-y-auto scrollbar-thin">
                <slot />
            </main>
        </div>

        <CommandPalette ref="palette" />

        <ToastHost />
    </div>
</template>
