<script setup>
defineProps({
    variant: {
        type: String,
        default: 'ghost',
        validator: (v) => ['ghost', 'filled'].includes(v),
    },
    ariaLabel: { type: String, required: true },
    disabled: { type: Boolean, default: false },
});
defineEmits(['click']);
</script>

<template>
    <button
        type="button"
        class="ui-icon-btn"
        :class="[`ui-icon-btn--${variant}`]"
        :disabled="disabled"
        :aria-label="ariaLabel"
        @click="$emit('click', $event)"
    >
        <slot />
    </button>
</template>

<style scoped>
.ui-icon-btn {
    width: 36px;
    height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--radius-pill);
    color: var(--color-text-subtle);
    transition: background var(--duration-fast) var(--ease-standard);
}

.ui-icon-btn--ghost { background: transparent; }
.ui-icon-btn--filled {
    background: var(--color-surface-raised);
}

.ui-icon-btn:active:not(:disabled) {
    background: var(--color-surface-raised);
}
.ui-icon-btn--filled:active:not(:disabled) {
    background: color-mix(in srgb, var(--color-text-strong) 8%, var(--color-surface-raised));
}
.ui-icon-btn:disabled { opacity: 0.45; cursor: not-allowed; }

@media (prefers-reduced-motion: reduce) {
    .ui-icon-btn { transition: none; }
}
</style>
