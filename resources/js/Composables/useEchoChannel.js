import { onBeforeUnmount, onMounted, ref, watch } from "vue";
import { useEchoInstance } from "../Services/echo";

const subscribers = new Map();

function acquire(name) {
    subscribers.set(name, (subscribers.get(name) ?? 0) + 1);

    return useEchoInstance().private(name);
}

function release(name) {
    const remaining = (subscribers.get(name) ?? 1) - 1;

    if (remaining > 0) {
        subscribers.set(name, remaining);

        return;
    }

    subscribers.delete(name);

    useEchoInstance().leaveChannel(`private-${name}`);
}

export function useEchoConnection(onReconnect) {
    const online = ref(true);

    let connection = null;
    let wasConnected = false;

    function onStateChange({ current }) {
        online.value = current === "connected";

        if (current !== "connected") {
            return;
        }

        if (wasConnected) {
            onReconnect();
        }

        wasConnected = true;
    }

    onMounted(() => {
        connection = useEchoInstance().connector.pusher?.connection ?? null;

        if (!connection) {
            return;
        }

        wasConnected = connection.state === "connected";
        online.value = wasConnected;

        connection.bind("state_change", onStateChange);
    });

    onBeforeUnmount(() => connection?.unbind("state_change", onStateChange));

    return { online };
}

export function usePrivateChannel(channelName, listeners) {
    let current = null;

    function resolveName() {
        return typeof channelName === "function" ? channelName() : channelName;
    }

    function subscribe() {
        const name = resolveName();

        if (current?.name === name) {
            return;
        }

        unsubscribe();

        if (!name) {
            return;
        }

        const channel = acquire(name);

        Object.entries(listeners).forEach(([event, handler]) => {
            channel.listen(`.${event}`, handler);
        });

        current = { name, channel };
    }

    function unsubscribe() {
        if (!current) {
            return;
        }

        Object.entries(listeners).forEach(([event, handler]) => {
            current.channel.stopListening(`.${event}`, handler);
        });

        release(current.name);
        current = null;
    }

    onMounted(subscribe);
    onBeforeUnmount(unsubscribe);

    if (typeof channelName === "function") {
        watch(channelName, subscribe);
    }

    return { subscribe, unsubscribe };
}
