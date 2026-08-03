<script setup>
import { ref, watch } from "vue";
import { Paperclip } from "lucide-vue-next";

const props = defineProps({
    message: { type: Object, required: true },
});

const SPEEDS = [1, 1.5, 2];

const player = ref(null);
const speedIndex = ref(0);

function cycleSpeed() {
    speedIndex.value = (speedIndex.value + 1) % SPEEDS.length;
}

watch([speedIndex, player], () => {
    if (player.value) {
        player.value.playbackRate = SPEEDS[speedIndex.value];
    }
});

function keepSpeed() {
    if (player.value) {
        player.value.playbackRate = SPEEDS[speedIndex.value];
    }
}
</script>

<template>
    <div v-if="message.media_url" class="space-y-1.5">
        <a
            v-if="message.type === 'image' || message.type === 'sticker'"
            :href="message.media_url"
            target="_blank"
            rel="noopener"
            @click.stop
        >
            <img
                :src="message.media_url"
                :alt="message.media_name ?? 'Imagem'"
                class="max-h-72 rounded-control object-cover"
            />
        </a>

        <div
            v-else-if="message.type === 'audio'"
            class="flex items-center gap-2"
        >
            <audio
                ref="player"
                :src="message.media_url"
                controls
                preload="metadata"
                class="h-9 w-56 sm:w-64"
                @loadedmetadata="keepSpeed"
                @play="keepSpeed"
                @click.stop
            />

            <button
                type="button"
                class="shrink-0 rounded-control border border-border px-1.5 py-0.5 text-[11px] font-medium text-content-muted transition hover:bg-surface-hover hover:text-content"
                :title="`Velocidade ${SPEEDS[speedIndex]}x`"
                @click.stop="cycleSpeed"
            >
                {{ SPEEDS[speedIndex] }}x
            </button>
        </div>

        <div v-else-if="message.type === 'video'" class="space-y-1">
            <video
                ref="player"
                :src="message.media_url"
                controls
                preload="metadata"
                class="max-h-72 w-full rounded-control"
                @loadedmetadata="keepSpeed"
                @play="keepSpeed"
                @click.stop
            />

            <button
                type="button"
                class="rounded-control border border-border px-1.5 py-0.5 text-[11px] font-medium text-content-muted transition hover:bg-surface-hover hover:text-content"
                :title="`Velocidade ${SPEEDS[speedIndex]}x`"
                @click.stop="cycleSpeed"
            >
                {{ SPEEDS[speedIndex] }}x
            </button>
        </div>

        <a
            v-else
            :href="message.media_url"
            target="_blank"
            rel="noopener"
            class="flex items-center gap-2 rounded-control border border-border px-2.5 py-1.5 text-xs text-content transition hover:bg-surface-hover"
            @click.stop
        >
            <Paperclip :size="14" />
            {{ message.media_name ?? "Arquivo" }}
        </a>

        <p
            v-if="message.transcription"
            class="max-w-64 whitespace-pre-wrap break-words rounded-control bg-surface-muted px-2 py-1 text-xs italic text-content-muted"
        >
            {{ message.transcription }}
        </p>
    </div>

    <span
        v-else-if="message.media_name"
        class="flex items-center gap-2 rounded-control border border-border px-2.5 py-1.5 text-xs text-content-muted"
    >
        <Paperclip :size="14" />
        {{ message.media_name }}
    </span>
</template>
