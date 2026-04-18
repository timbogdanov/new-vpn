<script setup>
import { computed } from 'vue';
import { store } from '../store.js';

const visible = computed(() => !!store.toast);
const kind = computed(() => store.toast?.kind || 'info');
const message = computed(() => store.toast?.message || '');

const color = computed(() => {
    switch (kind.value) {
        case 'success': return 'var(--color-success)';
        case 'error':   return 'var(--color-destructive)';
        case 'warning': return 'var(--color-warning)';
        default:        return 'var(--color-text-accent)';
    }
});
</script>

<template>
    <transition name="toast">
        <div
            v-if="visible"
            class="fixed left-1/2 -translate-x-1/2 px-4 py-2.5 card text-sm flex items-center gap-2 z-50"
            style="bottom: calc(var(--safe-bottom) + 84px); min-width: 180px; max-width: 88vw"
        >
            <span class="pill-dot" :style="{ background: color }" />
            <span>{{ message }}</span>
        </div>
    </transition>
</template>

<style scoped>
.toast-enter-active, .toast-leave-active {
    transition: opacity 180ms var(--ease-spring), transform 220ms var(--ease-spring);
}
.toast-enter-from, .toast-leave-to {
    opacity: 0;
    transform: translate(-50%, 8px);
}
.toast-enter-to, .toast-leave-from {
    opacity: 1;
    transform: translate(-50%, 0);
}
</style>
