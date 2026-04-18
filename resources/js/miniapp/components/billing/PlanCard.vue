<script setup>
import { computed } from 'vue';
import { t } from '../../i18n.js';
import StarsAmount from './StarsAmount.vue';
import { Button } from '../ui/index.js';

const props = defineProps({
    plan: { type: Object, required: true },
    busy: { type: Boolean, default: false },
});
const emit = defineEmits(['buy']);

const name = computed(() => t(props.plan.nameKey));
const description = computed(() => t(props.plan.descriptionKey));

const trafficLabel = computed(() => {
    if (props.plan.trafficCapBytes === null || props.plan.trafficCapBytes === undefined) {
        return t('billing.trafficUnlimited');
    }
    const gb = Math.round(props.plan.trafficCapBytes / (1024 ** 3));
    return t('billing.trafficCap', { gb });
});

const deviceLabel = computed(() =>
    props.plan.deviceLimit ? t('billing.deviceLimit', { n: props.plan.deviceLimit }) : null,
);

const cycle = computed(() => {
    if (props.plan.durationDays >= 360) return t('billing.perYear');
    return t('billing.perMonth');
});
</script>

<template>
    <article class="plan" :class="{ 'plan--highlight': plan.highlight }">
        <header class="plan__head">
            <div class="plan__name-row">
                <h3 class="plan__name">{{ name }}</h3>
                <span v-if="plan.highlight" class="plan__badge">{{ t('billing.saveBadge') }}</span>
            </div>
            <p class="plan__desc">{{ description }}</p>
        </header>

        <div class="plan__price">
            <StarsAmount :stars="plan.stars" :usd-estimate="plan.usdEstimate" size="lg" />
            <span class="plan__cycle">{{ cycle }}</span>
        </div>

        <ul class="plan__features">
            <li>{{ trafficLabel }}</li>
            <li v-if="deviceLabel">{{ deviceLabel }}</li>
        </ul>

        <Button
            block
            :variant="plan.highlight ? 'primary' : 'secondary'"
            :loading="busy"
            @click="emit('buy', plan)"
        >
            {{ t('billing.buyAction', { stars: new Intl.NumberFormat().format(plan.stars) }) }}
        </Button>
    </article>
</template>

<style scoped>
.plan {
    display: flex;
    flex-direction: column;
    gap: 14px;
    padding: 20px;
    background: var(--color-surface-raised);
    border-radius: var(--radius-lg);
}
.plan--highlight {
    background: var(--color-surface-raised);
    box-shadow: 0 0 0 1px var(--color-accent) inset;
    position: relative;
}
.plan--highlight::before {
    content: '';
    position: absolute;
    inset: 0;
    background: var(--color-accent-tint);
    border-radius: var(--radius-lg);
    pointer-events: none;
    z-index: 0;
}
.plan > * { position: relative; z-index: 1; }

.plan__head { display: flex; flex-direction: column; gap: 4px; }
.plan__name-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.plan__name {
    font-size: 17px;
    line-height: 24px;
    font-weight: 600;
    letter-spacing: -0.01em;
    color: var(--color-text-strong);
    margin: 0;
}
.plan__badge {
    font-size: 11px;
    line-height: 14px;
    font-weight: 600;
    color: var(--color-accent);
    background: var(--color-accent-tint);
    padding: 3px 8px;
    border-radius: var(--radius-sm);
    letter-spacing: 0.08em;
    text-transform: uppercase;
}
.plan__desc {
    color: var(--color-text-subtle);
    font-size: 13px;
    line-height: 18px;
    margin: 0;
}
.plan__price {
    display: flex;
    align-items: baseline;
    gap: 8px;
}
.plan__cycle {
    color: var(--color-text-hint);
    font-size: 12px;
    line-height: 16px;
}
.plan__features {
    list-style: none;
    padding: 0;
    margin: 0;
    display: flex;
    flex-direction: column;
    gap: 6px;
    color: var(--color-text-subtle);
    font-size: 13px;
    line-height: 18px;
}
.plan__features li {
    position: relative;
    padding-left: 14px;
}
.plan__features li::before {
    content: '';
    position: absolute;
    left: 0;
    top: 8px;
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: var(--color-text-hint);
}
</style>
