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
    padding: 0 4px 16px;
    max-width: calc(100% - 44px);
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
