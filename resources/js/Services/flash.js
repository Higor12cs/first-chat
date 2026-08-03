import { router } from "@inertiajs/vue3";
import { useToastStore } from "../Stores/toast";

export function registerFlashListener() {
    router.on("flash", (event) => {
        const flash = event.detail.flash ?? {};

        if (flash.success) {
            useToastStore().push({ type: "success", message: flash.success });
        }

        if (flash.warning) {
            useToastStore().push({ type: "warning", message: flash.warning });
        }

        if (flash.error) {
            useToastStore().push({ type: "danger", message: flash.error });
        }
    });
}
