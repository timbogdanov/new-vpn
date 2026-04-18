<script setup>
import { computed } from 'vue';
import { store } from '../store.js';

const visible = computed(() => !!store.toast);
const kind = computed(() => store.toast?.kind || 'info');
const message = computed(() => store.toast?.message || '');

const dotClass = computed(() => {
    switch (kind.value) {
        case 'success': return 'toast__dot--success';
        case 'error':   return 'toast__dot--error';
        case 'warning': return 'toast__dot--warning';
        default:        return 'toast__dot--info';
    }
});
</script>

<template>
    <transition name="toast">
        <div v-if="visible" class="toast" role="status" aria-live="polite">
            <span class="toast__dot" :class="dotClass" aria-hidden="true"></span>
            <span class="toast__msg">{{ message }}</span>
        </div>
    </transition>
</template>

<style scoped>
.toast {
    position: fixed;
    left: 50%;
    transform: translateX(-50%);
    bottom: calc(var(--safe-bottom) + 24px);
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    min-width: 200px;
    max-width: 360px;
    background: var(--color-surface-raised);
    color: var(--color-text-strong);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-toast);
    font-size: 13px;
    line-height: 18px;
    z-index: 50;
}

.toast__dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    flex-shrink: 0;
}
.toast__dot--success { background: var(--color-success); }
.toast__dot--error   { background: var(--color-danger); }
.toast__dot--warning { background: var(--color-warning); }
.toast__dot--info    { background: var(--color-text-subtle); }

.toast__msg { flex: 1 1 auto; }

.toast-enter-active, .toast-leave-active {
    transition: opacity var(--duration-slow) var(--ease-standard), transform var(--duration-slow) var(--ease-standard);
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
