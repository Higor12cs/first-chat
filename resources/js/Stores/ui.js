import { defineStore } from "pinia";
import { computed, ref, watch } from "vue";

const THEME_KEY = "theme";
const SOUND_KEY = "sound-alerts";
const SIDEBAR_KEY = "sidebar-collapsed";

const query = window.matchMedia("(prefers-color-scheme: dark)");

export const useUiStore = defineStore("ui", () => {
    const theme = ref(localStorage.getItem(THEME_KEY) ?? "system");
    const systemPrefersDark = ref(query.matches);
    const sidebarCollapsed = ref(localStorage.getItem(SIDEBAR_KEY) === "true");
    const mobileSidebarOpen = ref(false);
    const soundAlerts = ref(localStorage.getItem(SOUND_KEY) !== "false");

    const isDark = computed(() =>
        theme.value === "system" ? systemPrefersDark.value : theme.value === "dark",
    );

    function applyTheme() {
        document.documentElement.classList.toggle("dark", isDark.value);
        document.documentElement.style.colorScheme = isDark.value ? "dark" : "light";
    }

    function setTheme(value) {
        theme.value = value;
        localStorage.setItem(THEME_KEY, value);
    }

    function toggleTheme() {
        setTheme(isDark.value ? "light" : "dark");
    }

    query.addEventListener("change", (event) => (systemPrefersDark.value = event.matches));

    watch(isDark, applyTheme, { immediate: true });

    watch(sidebarCollapsed, (value) => localStorage.setItem(SIDEBAR_KEY, String(value)));

    watch(soundAlerts, (value) => localStorage.setItem(SOUND_KEY, String(value)));

    function toggleSoundAlerts() {
        soundAlerts.value = !soundAlerts.value;
    }

    return {
        theme,
        isDark,
        sidebarCollapsed,
        mobileSidebarOpen,
        soundAlerts,
        setTheme,
        toggleTheme,
        toggleSoundAlerts,
        applyTheme,
    };
});
