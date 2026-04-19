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
        <p v-if="plan.highlight" class="plan__tag">{{ t('billing.saveBadge') }}</p>

        <header class="plan__head">
            <h3 class="plan__name">{{ name }}</h3>
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
            variant="primary"
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
    border: 1px solid transparent;
}
.plan--highlight {
    border-color: var(--color-accent);
}

.plan__tag {
    margin: 0;
    font-size: 11px;
    line-height: 14px;
    font-weight: 600;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--color-accent);
}

.plan__head {
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.plan__name {
    margin: 0;
    font-size: 20px;
    line-height: 26px;
    font-weight: 600;
    letter-spacing: -0.01em;
    color: var(--color-text-strong);
}
.plan__desc {
    margin: 0;
    color: var(--color-text-subtle);
    font-size: 13px;
    line-height: 18px;
}

.plan__price {
    display: flex;
    align-items: baseline;
    gap: 8px;
    border-top: 1px solid var(--color-separator);
    border-bottom: 1px solid var(--color-separator);
    padding: 12px 0;
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
