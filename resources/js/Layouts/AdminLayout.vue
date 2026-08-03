<script setup>
import { computed, onMounted } from "vue";
import { Link, router, usePage } from "@inertiajs/vue3";
import { storeToRefs } from "pinia";
import { useUiStore } from "../Stores/ui";
import {
    ArrowLeft,
    Building2,
    ChartColumn,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    LogOut,
    Menu,
    Moon,
    ScrollText,
    ShieldCheck,
    Sun,
    UserPlus,
} from "lucide-vue-next";
import Avatar from "../Components/UI/Avatar.vue";
import Dropdown from "../Components/UI/Dropdown.vue";
import DropdownItem from "../Components/UI/DropdownItem.vue";
import SidebarLink from "../Components/UI/SidebarLink.vue";
import ToastHost from "../Components/UI/ToastHost.vue";

const page = usePage();
const ui = useUiStore();
const { sidebarCollapsed, mobileSidebarOpen } = storeToRefs(ui);

const user = computed(() => page.props.auth?.user);
const currentPath = computed(() => page.url.split("?")[0]);

const items = [
    { label: "Tenants", href: "/admin/tenants", icon: Building2 },
    {
        label: "Usuários da Plataforma",
        href: "/admin/usuarios",
        icon: UserPlus,
    },
    { label: "Uso da Plataforma", href: "/admin/uso", icon: ChartColumn },
    { label: "Auditoria Global", href: "/admin/auditoria", icon: ScrollText },
];

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

onMounted(() => ui.applyTheme());
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
                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-control bg-content text-content-inverted"
                >
                    <ShieldCheck :size="18" />
                </span>

                <div v-if="!sidebarCollapsed" class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-content">
                        Administração
                    </p>
                    <p class="truncate text-[11px] text-content-subtle">
                        {{ page.props.app.name }}
                    </p>
                </div>
            </div>

            <nav
                class="flex-1 overflow-y-auto scrollbar-thin py-3"
                :class="
                    sidebarCollapsed
                        ? 'flex flex-col items-center gap-1 px-0'
                        : 'space-y-1 px-2.5'
                "
            >
                <SidebarLink
                    v-for="item in items"
                    :key="item.href"
                    :href="item.href"
                    :label="item.label"
                    :icon="item.icon"
                    :active="isActive(item.href)"
                    :collapsed="sidebarCollapsed"
                    prefetch="hover"
                />
            </nav>

            <div class="shrink-0 border-t border-border">
                <Link
                    href="/painel"
                    class="flex h-11 items-center text-xs text-content-subtle transition hover:text-content"
                    :class="
                        sidebarCollapsed
                            ? 'justify-center px-0'
                            : 'gap-2.5 px-4'
                    "
                    title="Voltar ao Atendimento"
                >
                    <ArrowLeft :size="16" />
                    <span v-if="!sidebarCollapsed">Voltar ao Atendimento</span>
                </Link>

                <button
                    type="button"
                    class="hidden h-11 w-full items-center border-t border-border text-xs text-content-subtle transition hover:text-content lg:flex"
                    :class="
                        sidebarCollapsed
                            ? 'justify-center px-0'
                            : 'gap-2.5 px-4'
                    "
                    :title="
                        sidebarCollapsed ? 'Expandir Menu' : 'Recolher Menu'
                    "
                    @click="toggleSidebar"
                >
                    <component
                        :is="sidebarCollapsed ? ChevronRight : ChevronLeft"
                        :size="16"
                    />
                    <span v-if="!sidebarCollapsed">Recolher Menu</span>
                </button>
            </div>
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

                <p
                    class="min-w-0 flex-1 truncate text-sm font-medium text-content-muted"
                >
                    Área Administrativa
                </p>

                <div class="flex items-center gap-1.5">
                    <button
                        type="button"
                        class="rounded-control p-1.5 text-content-muted transition hover:bg-surface-hover hover:text-content"
                        title="Alternar Tema"
                        @click="ui.toggleTheme()"
                    >
                        <component :is="ui.isDark ? Sun : Moon" :size="18" />
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

                        <DropdownItem :icon="ArrowLeft" href="/painel"
                            >Voltar ao Atendimento</DropdownItem
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

        <ToastHost />
    </div>
</template>
