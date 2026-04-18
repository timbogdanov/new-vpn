<script setup>
import { t } from '../../i18n.js';

defineProps({
    title: { type: String, required: true },
    body: { type: String, default: '' },
    cta: { type: String, default: '' },
    severity: {
        type: String,
        default: 'info',
        validator: (v) => ['info', 'warn', 'danger'].includes(v),
    },
});
const emit = defineEmits(['cta']);
</script>

<template>
    <div class="banner" :class="`banner--${severity}`">
        <div class="banner__copy">
            <p class="banner__title">{{ title }}</p>
            <p v-if="body" class="banner__body">{{ body }}</p>
        </div>
        <button type="button" class="banner__cta" @click="emit('cta')">
            {{ cta || t('billing.upgrade') }}
        </button>
    </div>
</template>

<style scoped>
.banner {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 14px;
    border-radius: var(--radius-md);
    background: var(--color-surface-raised);
    border-left: 3px solid var(--color-text-subtle);
}
.banner--info   { border-left-color: var(--color-accent); }
.banner--warn   { border-left-color: var(--color-warning); }
.banner--danger { border-left-color: var(--color-danger); }

.banner__copy { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
.banner__title {
    font-size: 13px;
    line-height: 18px;
    font-weight: 500;
    color: var(--color-text-strong);
    margin: 0;
}
.banner__body {
    font-size: 12px;
    line-height: 16px;
    color: var(--color-text-subtle);
    margin: 0;
}
.banner__cta {
    flex-shrink: 0;
    padding: 6px 4px;
    font-size: 13px;
    line-height: 18px;
    font-weight: 500;
    color: var(--color-accent);
    white-space: nowrap;
    transition: color var(--duration-fast) var(--ease-standard);
}
.banner__cta:active { color: var(--color-accent-pressed); }
</style>
