<script setup>
import { onBeforeUnmount, onMounted, ref } from "vue";

defineProps({
    align: { type: String, default: "right" },
    width: { type: String, default: "w-52" },
    direction: { type: String, default: "down" },
});

const open = ref(false);
const root = ref(null);

function close(event) {
    if (root.value && !root.value.contains(event.target)) {
        open.value = false;
    }
}

onMounted(() => document.addEventListener("click", close));
onBeforeUnmount(() => document.removeEventListener("click", close));
</script>

<template>
    <div ref="root" class="relative">
        <div @click="open = !open">
            <slot name="trigger" :open="open" />
        </div>

        <Transition
            enter-active-class="transition duration-100"
            enter-from-class="opacity-0 scale-95"
            leave-active-class="transition duration-75"
            leave-to-class="opacity-0 scale-95"
        >
            <div
                v-if="open"
                class="absolute z-40 overflow-hidden rounded-card border border-border-strong bg-surface p-1"
                :class="[
                    width,
                    align === 'right' ? 'right-0' : 'left-0',
                    direction === 'up' ? 'bottom-full mb-1.5' : 'mt-1.5',
                ]"
                @click="open = false"
            >
                <slot />
            </div>
        </Transition>
    </div>
</template>
