<script setup>
import { computed } from 'vue';

const props = defineProps({
    emoji: { type: String, default: '' },
    code: { type: String, default: '' },
    size: { type: Number, default: 40 },
});

const glyph = computed(() => {
    if (props.emoji) return props.emoji;
    if (props.code && props.code.length === 2) {
        const a = props.code.charCodeAt(0) - 65 + 0x1F1E6;
        const b = props.code.charCodeAt(1) - 65 + 0x1F1E6;
        return String.fromCodePoint(a) + String.fromCodePoint(b);
    }
    return '🌐';
});
</script>

<template>
    <div
        class="flex items-center justify-center rounded-full"
        :style="{
            width: size + 'px',
            height: size + 'px',
            background: 'color-mix(in srgb, var(--color-button) 10%, transparent)',
            fontSize: (size * 0.55) + 'px',
            lineHeight: 1,
        }"
    >
        <span style="filter: saturate(1.1)">{{ glyph }}</span>
    </div>
</template>
