<script setup>
import { computed } from "vue";
import { initials } from "../../Utils/format";

const props = defineProps({
    name: { type: String, default: "" },
    src: { type: String, default: null },
    size: { type: Number, default: 36 },
});

const label = computed(() => initials(props.name));
</script>

<template>
    <span
        class="inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary-soft text-primary"
        :style="{ width: `${size}px`, height: `${size}px`, fontSize: `${Math.round(size / 2.8)}px` }"
    >
        <img v-if="src" :src="src" :alt="name" class="h-full w-full object-cover" />
        <span v-else class="font-semibold">{{ label }}</span>
    </span>
</template>
