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
    background: rgba(0, 0, 0, 0.55);
}

.ui-sheet__panel {
    position: relative;
    background: var(--color-surface);
    border-top-left-radius: var(--radius-md);
    border-top-right-radius: var(--radius-md);
    padding: 10px 20px calc(var(--safe-bottom) + 20px);
    box-shadow: var(--shadow-floating);
    max-height: 85vh;
    overflow-y: auto;
}

.ui-sheet__handle {
    width: 36px;
    height: 4px;
    border-radius: 2px;
    background: var(--color-gray-4);
    margin: 4px auto 12px;
}

.ui-sheet-enter-active,
.ui-sheet-leave-active { transition: opacity var(--duration-base) var(--ease-out); }
.ui-sheet-enter-active .ui-sheet__panel,
.ui-sheet-leave-active .ui-sheet__panel {
    transition: transform var(--duration-slow) var(--ease-spring);
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
