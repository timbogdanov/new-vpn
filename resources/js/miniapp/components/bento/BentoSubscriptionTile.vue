<script setup>
import { computed } from 'vue';
import { Sparkles } from 'lucide-vue-next';
import BentoTile from './BentoTile.vue';
import { t } from '../../i18n.js';
import { activeSubscription, daysUntilExpiry, isTrialing, isExpired } from '../../billing.js';
import { hap } from '../../telegram.js';

const emit = defineEmits(['paywall']);

const sub = computed(() => activeSubscription());
const days = computed(() => daysUntilExpiry());
const trialing = computed(() => isTrialing());
const expired = computed(() => isExpired());

const headline = computed(() => {
    if (expired.value || !sub.value) return t('billing.noActive');
    if (trialing.value) return t('billing.trialActive', { days: days.value });
    return t('billing.proActive', { days: days.value });
});

function open() {
    hap.select();
    emit('paywall');
}
</script>

<template>
    <BentoTile :span="trialing || expired ? '2' : '1'" surface="mesh" interactive @click="open">
        <div class="sub-tile">
            <header class="sub-tile__head">
                <span class="sub-tile__eyebrow">{{ t('billing.title') }}</span>
                <Sparkles :size="18" :stroke-width="1.75" />
            </header>
            <p class="sub-tile__h">{{ headline }}</p>
            <p class="sub-tile__cta">{{ trialing || expired ? t('billing.upgrade') : t('billing.manage') }} →</p>
        </div>
    </BentoTile>
</template>

<style scoped>
.sub-tile { display: flex; flex-direction: column; gap: 8px; height: 100%; }
.sub-tile__head { display: flex; align-items: center; justify-content: space-between; color: var(--color-text-hint); }
.sub-tile__eyebrow { font-size: var(--text-caption); text-transform: uppercase; letter-spacing: 0.06em; font-weight: 600; }
.sub-tile__h {
    font-family: var(--font-display);
    font-size: var(--text-title);
    line-height: var(--text-title--line-height);
    font-weight: 600;
    margin: auto 0 0;
}
.sub-tile__cta { color: var(--color-accent); font-size: var(--text-label); margin: 0; font-weight: 600; }
</style>
