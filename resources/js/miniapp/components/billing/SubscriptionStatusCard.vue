<script setup>
import { computed } from 'vue';
import { t } from '../../i18n.js';
import { activeSubscription, daysUntilExpiry, hoursUntilExpiry, isTrialing, isExpired } from '../../billing.js';
import { Button, Chip } from '../ui/index.js';

const emit = defineEmits(['upgrade', 'history']);

const sub = computed(() => activeSubscription());
const days = computed(() => daysUntilExpiry());
const hours = computed(() => hoursUntilExpiry());
const trialing = computed(() => isTrialing());
const expired = computed(() => isExpired());

const heading = computed(() => {
    if (!sub.value || expired.value) return t('billing.noActive');
    if (trialing.value) return t('billing.trialActive', { days: days.value });
    return t('billing.proActive', { days: days.value });
});

const cta = computed(() => {
    if (expired.value) return t('billing.renew');
    if (trialing.value) return t('billing.upgrade');
    return t('billing.manage');
});

const chipTone = computed(() => {
    if (expired.value) return 'danger';
    if (trialing.value && hours.value <= 48) return 'warning';
    if (trialing.value) return 'neutral';
    return 'accent';
});
</script>

<template>
    <section class="sub-card">
        <header class="sub-card__head">
            <Chip :tone="chipTone" dot>{{ trialing ? t('billing.plans.trial_7d.name') : t('billing.title') }}</Chip>
        </header>
        <p class="sub-card__heading">{{ heading }}</p>
        <p v-if="sub && !expired && hours <= 48" class="sub-card__hint">
            {{ t('billing.expiring', { hours }) }}
        </p>

        <div class="sub-card__actions">
            <Button block @click="emit('upgrade')">{{ cta }}</Button>
            <Button variant="ghost" size="sm" block @click="emit('history')">{{ t('billing.history') }}</Button>
        </div>
    </section>
</template>

<style scoped>
.sub-card {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 16px;
    background: var(--color-surface);
    border-radius: var(--radius-lg);
}
.sub-card__head { display: flex; align-items: center; justify-content: flex-start; gap: 8px; }
.sub-card__heading {
    font-size: 17px;
    line-height: 24px;
    font-weight: 500;
    color: var(--color-text-strong);
    margin: 0;
}
.sub-card__hint {
    color: var(--color-text-subtle);
    font-size: 13px;
    line-height: 18px;
    margin: 0;
}
.sub-card__actions {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 8px;
}
</style>
