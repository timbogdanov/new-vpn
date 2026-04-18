<script setup>
const props = defineProps({
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
    border-radius: var(--radius-sm);
    color: var(--color-text);
    transition: background var(--duration-base) var(--ease-out), transform var(--duration-fast) var(--ease-out);
}

.ui-icon-btn--ghost { background: transparent; }
.ui-icon-btn--filled {
    background: var(--color-surface-raised);
    box-shadow: 0 0 0 1px var(--color-separator) inset;
}

.ui-icon-btn:active:not(:disabled) { transform: scale(0.94); }
.ui-icon-btn:disabled { opacity: 0.45; cursor: not-allowed; }

@media (prefers-reduced-motion: reduce) {
    .ui-icon-btn { transition: none; }
    .ui-icon-btn:active:not(:disabled) { transform: none; }
}
</style>
