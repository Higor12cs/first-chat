import "../css/app.css";

import { createApp, h } from "vue";
import { createInertiaApp } from "@inertiajs/vue3";
import { createPinia } from "pinia";
import AdminLayout from "./Layouts/AdminLayout.vue";
import AppLayout from "./Layouts/AppLayout.vue";
import GuestLayout from "./Layouts/GuestLayout.vue";
import { startEcho } from "./Services/echo";
import { registerFlashListener } from "./Services/flash";

const appName = import.meta.env.VITE_APP_NAME ?? "FirstChat";

function layoutFor(name) {
  if (name.startsWith("Auth/")) {
    return GuestLayout;
  }

  return name.startsWith("Admin/") ? AdminLayout : AppLayout;
}

createInertiaApp({
  title: (title) => (title ? `${title} · ${appName}` : appName),
  progress: {
    color: "var(--primary)",
  },
  resolve: async (name) => {
    const pages = import.meta.glob("./Pages/**/*.vue");
    const page = await pages[`./Pages/${name}.vue`]();

    page.default.layout = page.default.layout ?? layoutFor(name);

    return page;
  },
  setup({ el, App, props, plugin }) {
    startEcho();
    registerFlashListener();

    createApp({ render: () => h(App, props) })
      .use(plugin)
      .use(createPinia())
      .mount(el);
  },
});
