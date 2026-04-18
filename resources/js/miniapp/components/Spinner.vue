<script setup>
defineProps({
    overlay: { type: Boolean, default: false },
    size: { type: Number, default: 22 },
});
</script>

<template>
    <div :class="overlay ? 'spinner-overlay' : 'spinner-inline'" role="status" aria-label="Loading">
        <svg :width="size" :height="size" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity="0.18" stroke-width="2.5" />
            <path
                d="M21 12a9 9 0 0 0-9-9"
                stroke="var(--color-accent)"
                stroke-width="2.5"
                stroke-linecap="round"
                class="spinner__arc"
            />
        </svg>
    </div>
</template>

<style scoped>
.spinner-overlay {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(2px);
    color: var(--color-text-hint);
}
.spinner-inline {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--color-text-hint);
}
.spinner__arc {
    transform-origin: 12px 12px;
    animation: spinner-spin 0.85s linear infinite;
}
@keyframes spinner-spin {
    to { transform: rotate(360deg); }
}
@media (prefers-reduced-motion: reduce) {
    .spinner__arc { animation-duration: 2s; }
}
</style>
