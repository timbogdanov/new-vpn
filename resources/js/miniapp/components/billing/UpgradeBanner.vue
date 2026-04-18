<script setup>
import { Button } from '../ui/index.js';
import { t } from '../../i18n.js';

defineProps({
    title: { type: String, required: true },
    body: { type: String, default: '' },
    cta: { type: String, default: '' },
    severity: { type: String, default: 'info' }, // info | warn | danger
});
const emit = defineEmits(['cta']);
</script>

<template>
    <div class="upgrade-banner" :class="`upgrade-banner--${severity}`">
        <div class="upgrade-banner__copy">
            <p class="upgrade-banner__title">{{ title }}</p>
            <p v-if="body" class="upgrade-banner__body">{{ body }}</p>
        </div>
        <Button size="sm" :variant="severity === 'danger' ? 'destructive' : 'primary'" @click="emit('cta')">
            {{ cta || t('billing.upgrade') }}
        </Button>
    </div>
</template>

<style scoped>
.upgrade-banner {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
    border-radius: var(--radius-md);
    border: 1px solid var(--color-separator);
    background: var(--color-surface);
}
.upgrade-banner--warn {
    background: color-mix(in oklch, var(--color-warning) 12%, var(--color-surface));
    border-color: color-mix(in oklch, var(--color-warning) 35%, var(--color-separator));
}
.upgrade-banner--danger {
    background: color-mix(in oklch, var(--color-destructive) 12%, var(--color-surface));
    border-color: color-mix(in oklch, var(--color-destructive) 35%, var(--color-separator));
}
.upgrade-banner__copy { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.upgrade-banner__title { font-size: var(--text-body); font-weight: 600; margin: 0; }
.upgrade-banner__body { font-size: var(--text-label); color: var(--color-text-subtle); margin: 0; }
</style>
