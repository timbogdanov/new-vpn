<script setup>
defineProps({
    overlay: { type: Boolean, default: false },
    size: { type: Number, default: 24 },
});
</script>

<template>
    <div
        :class="overlay ? 'absolute inset-0 flex items-center justify-center' : 'inline-flex items-center justify-center'"
        :style="overlay ? 'backdrop-filter: blur(2px);' : ''"
        role="status"
        aria-label="Loading"
    >
        <svg :width="size" :height="size" viewBox="0 0 24 24" fill="none">
            <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-opacity="0.18" stroke-width="3" />
            <path
                d="M21 12a9 9 0 0 0-9-9"
                stroke="currentColor"
                stroke-width="3"
                stroke-linecap="round"
                style="color: var(--color-text-accent); transform-origin: 12px 12px; animation: spin 0.9s linear infinite"
            />
        </svg>
    </div>
</template>

<style scoped>
@keyframes spin {
    to { transform: rotate(360deg); }
}
</style>
