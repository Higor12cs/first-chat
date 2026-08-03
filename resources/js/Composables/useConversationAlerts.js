import { computed } from "vue";
import { usePage } from "@inertiajs/vue3";
import { useUiStore } from "../Stores/ui";
import { usePrivateChannel } from "./useEchoChannel";
import { playAlert } from "../Services/sound";

export function useConversationAlerts() {
    const page = usePage();
    const ui = useUiStore();

    const tenantId = computed(() => page.props.tenant?.id ?? null);
    const userId = computed(() => page.props.auth?.user?.id ?? null);
    const queueIds = computed(() => page.props.auth?.service_queue_ids ?? []);

    const announced = new Set();

    function mine(conversation) {
        return (
            conversation?.assigned_user?.id != null &&
            conversation.assigned_user.id === userId.value
        );
    }

    function waitingForMyQueue(conversation) {
        return (
            conversation?.section === "waiting" &&
            queueIds.value.includes(conversation?.service_queue?.id)
        );
    }

    function onWaiting(conversation) {
        if (!waitingForMyQueue(conversation)) {
            announced.delete(conversation?.id);

            return;
        }

        if (announced.has(conversation.id)) {
            return;
        }

        announced.add(conversation.id);
        play("waiting");
    }

    function play(name) {
        if (ui.soundAlerts) {
            playAlert(name);
        }
    }

    usePrivateChannel(
        () =>
            tenantId.value ? `tenants.${tenantId.value}.conversations` : null,
        {
            "message.received": (event) => {
                if (mine(event.conversation)) {
                    play("message");

                    return;
                }

                onWaiting(event.conversation);
            },
            "conversation.updated": (event) => onWaiting(event.conversation),
        },
    );
}
