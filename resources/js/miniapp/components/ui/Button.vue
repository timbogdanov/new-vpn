<script setup>
import { computed } from 'vue';

const props = defineProps({
    variant: {
        type: String,
        default: 'primary',
        validator: (v) => ['primary', 'secondary', 'ghost', 'destructive'].includes(v),
    },
    size: {
        type: String,
        default: 'md',
        validator: (v) => ['md', 'sm'].includes(v),
    },
    block: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    loading: { type: Boolean, default: false },
    type: { type: String, default: 'button' },
});

const emit = defineEmits(['click']);

const variantClass = computed(() => `ui-btn--${props.variant}`);
const sizeClass = computed(() => `ui-btn--${props.size}`);

function onClick(e) {
    if (props.disabled || props.loading) return;
    emit('click', e);
}
</script>

<template>
    <button
        :type="type"
        class="ui-btn"
        :class="[variantClass, sizeClass, block ? 'w-full' : '']"
        :disabled="disabled || loading"
        :aria-busy="loading || undefined"
        @click="onClick"
    >
        <span v-if="loading" class="ui-btn__spinner" aria-hidden="true"></span>
        <span class="ui-btn__body" :class="{ 'ui-btn__body--dim': loading }">
            <slot />
        </span>
    </button>
</template>

<style scoped>
.ui-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    border-radius: var(--radius-md);
    font-family: var(--font-sans);
    font-weight: 500;
    transition:
        background var(--duration-base) var(--ease-standard),
        color var(--duration-base) var(--ease-standard),
        opacity var(--duration-base) var(--ease-standard);
    position: relative;
    isolation: isolate;
    white-space: nowrap;
}

.ui-btn--md { padding: 12px 18px; font-size: 15px; line-height: 20px; min-height: 44px; }
.ui-btn--sm { padding: 8px 14px;  font-size: 13px; line-height: 18px; min-height: 36px; }

.ui-btn--primary {
    background: var(--color-accent);
    color: var(--color-accent-text);
}
.ui-btn--primary:hover:not(:disabled) { background: var(--color-accent-hover); }
.ui-btn--primary:active:not(:disabled) { background: var(--color-accent-pressed); }

.ui-btn--secondary {
    background: var(--color-surface-raised);
    color: var(--color-text-strong);
}
.ui-btn--secondary:active:not(:disabled) {
    background: color-mix(in srgb, var(--color-text-strong) 8%, var(--color-surface-raised));
}

.ui-btn--ghost {
    background: transparent;
    color: var(--color-text-strong);
}
.ui-btn--ghost:active:not(:disabled) {
    background: color-mix(in srgb, var(--color-text-strong) 6%, transparent);
}

.ui-btn--destructive {
    background: var(--color-surface-raised);
    color: var(--color-danger);
}
.ui-btn--destructive:active:not(:disabled) {
    background: color-mix(in srgb, var(--color-danger) 10%, var(--color-surface-raised));
}

.ui-btn:disabled { opacity: 0.4; cursor: not-allowed; }

.ui-btn__spinner {
    position: absolute;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    border: 2px solid currentColor;
    border-top-color: transparent;
    opacity: 0.9;
    animation: ui-btn-spin 800ms linear infinite;
}

.ui-btn__body--dim { opacity: 0; }

@keyframes ui-btn-spin {
    to { transform: rotate(360deg); }
}

@media (prefers-reduced-motion: reduce) {
    .ui-btn { transition: none; }
    .ui-btn__spinner { animation-duration: 2s; }
}
</style>
