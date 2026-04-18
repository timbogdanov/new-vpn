<script setup>
defineProps({
    tone: {
        type: String,
        default: 'neutral',
        validator: (v) => ['neutral', 'success', 'warning', 'danger', 'accent'].includes(v),
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
    padding: 3px 8px;
    border-radius: var(--radius-sm);
    font-size: 11px;
    line-height: 14px;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
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
    background: color-mix(in srgb, var(--color-text-subtle) 10%, transparent);
    color: var(--color-text-subtle);
}
.ui-chip--accent {
    background: var(--color-accent-tint);
    color: var(--color-accent);
}
.ui-chip--success {
    background: color-mix(in srgb, var(--color-success) 12%, transparent);
    color: var(--color-success);
}
.ui-chip--warning {
    background: color-mix(in srgb, var(--color-warning) 12%, transparent);
    color: var(--color-warning);
}
.ui-chip--danger {
    background: color-mix(in srgb, var(--color-danger) 12%, transparent);
    color: var(--color-danger);
}
</style>
