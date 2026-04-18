<script setup>
import { computed } from 'vue';
import { t } from '../../i18n.js';
import { activeSubscription, daysUntilExpiry, hoursUntilExpiry, isTrialing, isExpired } from '../../billing.js';
import { Card, Button, Chip } from '../ui/index.js';

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
</script>

<template>
    <Card padding="md">
        <div class="sub-card">
            <div class="sub-card__head">
                <p class="sub-card__title">{{ t('billing.title') }}</p>
                <Chip v-if="trialing">{{ t('billing.plans.trial_7d.name') }}</Chip>
            </div>
            <p class="sub-card__heading">{{ heading }}</p>
            <p v-if="sub && !expired && hours <= 48" class="sub-card__hint">
                {{ t('billing.expiring', { hours }) }}
            </p>

            <div class="sub-card__actions">
                <Button block @click="emit('upgrade')">{{ cta }}</Button>
                <Button variant="ghost" size="sm" @click="emit('history')">{{ t('billing.history') }}</Button>
            </div>
        </div>
    </Card>
</template>

<style scoped>
.sub-card { display: flex; flex-direction: column; gap: 8px; }
.sub-card__head { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
.sub-card__title { color: var(--color-text-hint); font-size: var(--text-label); margin: 0; text-transform: uppercase; letter-spacing: 0.04em; }
.sub-card__heading {
    font-family: var(--font-display);
    font-size: var(--text-title);
    line-height: var(--text-title--line-height);
    margin: 0;
    font-weight: 600;
}
.sub-card__hint { color: var(--color-text-subtle); font-size: var(--text-label); margin: 0; }
.sub-card__actions { display: flex; flex-direction: column; gap: 6px; margin-top: 8px; }
</style>
