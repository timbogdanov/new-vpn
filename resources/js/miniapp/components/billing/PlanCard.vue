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
    gap: 16px;
    padding: 20px;
    background: var(--color-surface);
    border: 1px solid var(--color-separator);
    border-radius: var(--radius-md);
}
.plan--highlight {
    border-color: color-mix(in oklch, var(--color-accent) 60%, transparent);
    box-shadow: 0 0 0 1px color-mix(in oklch, var(--color-accent) 60%, transparent) inset;
    background:
        linear-gradient(
            135deg,
            color-mix(in oklch, var(--color-accent) 8%, transparent),
            transparent 60%
        ),
        var(--color-surface);
}
.plan__head { display: flex; flex-direction: column; gap: 6px; }
.plan__name-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; }
.plan__name { font-size: var(--text-title); line-height: var(--text-title--line-height); font-weight: 600; margin: 0; }
.plan__badge {
    font-size: var(--text-caption);
    font-weight: 600;
    color: var(--color-accent-text);
    background: var(--color-accent);
    padding: 4px 8px;
    border-radius: var(--radius-pill);
    letter-spacing: 0.02em;
    text-transform: uppercase;
}
.plan__desc { color: var(--color-text-subtle); font-size: var(--text-body); line-height: var(--text-body--line-height); margin: 0; }
.plan__price { display: flex; align-items: baseline; gap: 8px; }
.plan__cycle { color: var(--color-text-hint); font-size: var(--text-label); }
.plan__features { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 6px; color: var(--color-text-subtle); font-size: var(--text-body); }
.plan__features li::before { content: '✓'; color: var(--color-success); margin-right: 8px; font-weight: 700; }
</style>
