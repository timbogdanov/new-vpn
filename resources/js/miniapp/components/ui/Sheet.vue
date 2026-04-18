<script setup>
import { onBeforeUnmount, watch } from 'vue';

const props = defineProps({
    open: { type: Boolean, default: false },
    ariaLabel: { type: String, default: 'Dialog' },
});
const emit = defineEmits(['update:open', 'close']);

function close() {
    emit('update:open', false);
    emit('close');
}

function onKey(e) {
    if (e.key === 'Escape' && props.open) close();
}

watch(
    () => props.open,
    (open) => {
        if (open) {
            document.addEventListener('keydown', onKey);
            document.body.style.overflow = 'hidden';
        } else {
            document.removeEventListener('keydown', onKey);
            document.body.style.overflow = '';
        }
    },
);

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKey);
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <Transition name="ui-sheet">
            <div v-if="open" class="ui-sheet-root" role="dialog" aria-modal="true" :aria-label="ariaLabel">
                <div class="ui-sheet__scrim" @click="close" aria-hidden="true"></div>
                <div class="ui-sheet__panel">
                    <div class="ui-sheet__handle" aria-hidden="true"></div>
                    <button
                        type="button"
                        class="ui-sheet__close"
                        aria-label="Close"
                        @click="close"
                    >
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                    <div class="ui-sheet__content">
                        <slot />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.ui-sheet-root {
    position: fixed;
    inset: 0;
    z-index: 1000;
    display: flex;
    flex-direction: column;
    justify-content: flex-end;
}

.ui-sheet__scrim {
    position: absolute;
    inset: 0;
    background: rgba(0, 0, 0, 0.6);
}

.ui-sheet__panel {
    position: relative;
    background: var(--color-surface);
    border-top-left-radius: var(--radius-xl);
    border-top-right-radius: var(--radius-xl);
    padding: 12px 20px calc(var(--safe-bottom) + 24px);
    box-shadow: var(--shadow-sheet);
    max-height: 85vh;
    overflow-y: auto;
}

.ui-sheet__handle {
    width: 32px;
    height: 4px;
    border-radius: 2px;
    background: var(--color-gray-4);
    margin: 4px auto 8px;
}

.ui-sheet__close {
    position: absolute;
    top: 12px;
    right: 12px;
    width: 36px;
    height: 36px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: var(--radius-pill);
    color: var(--color-text-subtle);
    z-index: 2;
    transition: background var(--duration-fast) var(--ease-standard);
}
.ui-sheet__close:active {
    background: var(--color-surface-raised);
}

.ui-sheet__content {
    padding-top: 8px;
}

.ui-sheet-enter-active,
.ui-sheet-leave-active { transition: opacity var(--duration-slow) var(--ease-standard); }
.ui-sheet-enter-active .ui-sheet__panel,
.ui-sheet-leave-active .ui-sheet__panel {
    transition: transform var(--duration-slow) var(--ease-emphasized);
}

.ui-sheet-enter-from,
.ui-sheet-leave-to { opacity: 0; }
.ui-sheet-enter-from .ui-sheet__panel,
.ui-sheet-leave-to .ui-sheet__panel {
    transform: translateY(100%);
}

@media (prefers-reduced-motion: reduce) {
    .ui-sheet-enter-active,
    .ui-sheet-leave-active,
    .ui-sheet-enter-active .ui-sheet__panel,
    .ui-sheet-leave-active .ui-sheet__panel { transition: none; }
}
</style>
