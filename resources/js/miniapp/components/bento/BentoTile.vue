<script setup>
defineProps({
    span: { type: String, default: '1', validator: (v) => ['1', '2', 'full'].includes(v) },
    rows: { type: Number, default: 1 },
    surface: { type: String, default: 'surface', validator: (v) => ['surface', 'raised', 'aurora', 'mesh', 'transparent'].includes(v) },
    interactive: { type: Boolean, default: false },
});
const emit = defineEmits(['click']);
</script>

<template>
    <component
        :is="interactive ? 'button' : 'article'"
        class="bento-tile"
        :class="[
            `bento-tile--span-${span}`,
            `bento-tile--rows-${rows}`,
            `bento-tile--${surface}`,
            interactive ? 'bento-tile--interactive' : '',
        ]"
        :type="interactive ? 'button' : undefined"
        @click="interactive ? emit('click', $event) : null"
    >
        <slot />
    </component>
</template>

<style scoped>
.bento-tile {
    position: relative;
    isolation: isolate;
    border-radius: var(--radius-lg);
    padding: 16px;
    min-height: 96px;
    display: flex;
    flex-direction: column;
    text-align: left;
    transition:
        transform var(--duration-fast) var(--ease-out),
        box-shadow var(--duration-base) var(--ease-out);
    overflow: hidden;
}

.bento-tile--span-1 { grid-column: span 1; }
.bento-tile--span-2 { grid-column: span 2; }
.bento-tile--span-full { grid-column: 1 / -1; }
.bento-tile--rows-2 { grid-row: span 2; }

.bento-tile--surface {
    background: var(--color-surface);
    box-shadow: var(--shadow-card);
}
.bento-tile--raised {
    background: var(--color-surface-raised);
    box-shadow: var(--shadow-card);
}
.bento-tile--aurora {
    background: var(--gradient-aurora);
    color: #fff;
    box-shadow: var(--shadow-hero);
}
.bento-tile--mesh {
    background: var(--gradient-mesh), var(--color-surface);
    box-shadow: var(--shadow-card);
}
.bento-tile--transparent {
    background: transparent;
}

.bento-tile--interactive {
    cursor: pointer;
    border: 0;
    width: 100%;
    color: inherit;
    font: inherit;
}
.bento-tile--interactive:active { transform: scale(0.985); }

@media (max-width: 360px) {
    .bento-tile--span-2,
    .bento-tile--span-full { grid-column: 1; }
    .bento-tile--rows-2 { grid-row: span 1; }
}

@media (prefers-reduced-motion: reduce) {
    .bento-tile { transition: none; }
    .bento-tile--interactive:active { transform: none; }
}
</style>
