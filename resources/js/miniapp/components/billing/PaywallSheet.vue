<script setup>
import { computed, ref } from 'vue';
import Sheet from '../ui/Sheet.vue';
import PlanCard from './PlanCard.vue';
import { t } from '../../i18n.js';
import { store } from '../../store.js';
import { buyPlan } from '../../billing.js';

const emit = defineEmits(['update:open']);

defineProps({
    open: { type: Boolean, default: false },
});

const busyKey = ref(null);

const plans = computed(() => store.subscription?.plans || []);

async function onBuy(plan) {
    busyKey.value = plan.key;
    try {
        const status = await buyPlan(plan.key, {
            onActivated: () => emit('update:open', false),
        });
        if (status === 'paid') {
            emit('update:open', false);
        }
    } finally {
        busyKey.value = null;
    }
}
</script>

<template>
    <Sheet :open="open" :aria-label="t('billing.upgrade')" @update:open="emit('update:open', $event)">
        <header class="paywall__head">
            <p class="paywall__eyebrow">{{ t('billing.title') }}</p>
            <h2 class="paywall__title font-display">{{ t('billing.upgrade') }}</h2>
        </header>
        <div class="paywall__plans">
            <PlanCard
                v-for="p in plans"
                :key="p.key"
                :plan="p"
                :busy="busyKey === p.key"
                @buy="onBuy"
            />
        </div>
    </Sheet>
</template>

<style scoped>
.paywall__head {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 0 4px 20px;
    max-width: calc(100% - 44px);
}
.paywall__eyebrow {
    margin: 0;
    font-size: 11px;
    line-height: 14px;
    font-weight: 600;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--color-text-hint);
}
.paywall__title {
    margin: 0;
    font-size: 28px;
    line-height: 34px;
    color: var(--color-text-strong);
}
.paywall__plans {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding-bottom: 12px;
}
</style>
