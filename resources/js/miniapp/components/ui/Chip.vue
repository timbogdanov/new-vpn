<script setup>
defineProps({
    tone: {
        type: String,
        default: 'neutral',
        validator: (v) => ['neutral', 'success', 'warning', 'accent'].includes(v),
    },
    dot: { type: Boolean, default: false },
});
</script>

<template>
    <span class="ui-chip" :class="[`ui-chip--${tone}`]">
        <span v-if="dot" class="ui-chip__dot" aria-hidden="true"></span>
        <slot />
    </span>
</template>

<style scoped>
.ui-chip {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 3px 10px;
    border-radius: var(--radius-pill);
    font-size: 12px;
    line-height: 16px;
    font-weight: 500;
    font-variant-numeric: tabular-nums;
    white-space: nowrap;
}

.ui-chip__dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: currentColor;
    flex-shrink: 0;
}

.ui-chip--neutral {
    background: var(--color-surface-raised);
    color: var(--color-text-subtle);
    box-shadow: 0 0 0 1px var(--color-separator) inset;
}
.ui-chip--accent {
    background: color-mix(in srgb, var(--color-accent) 14%, transparent);
    color: var(--color-accent);
}
.ui-chip--success {
    background: color-mix(in srgb, var(--color-success) 14%, transparent);
    color: var(--color-success);
}
.ui-chip--warning {
    background: color-mix(in srgb, var(--color-warning) 14%, transparent);
    color: var(--color-warning);
}
</style>
