<script setup>
import { computed } from 'vue';
import { Zap } from 'lucide-vue-next';
import BentoTile from './BentoTile.vue';
import { Button } from '../ui/index.js';
import { store } from '../../store.js';
import { t } from '../../i18n.js';
import { hap } from '../../telegram.js';
import { isExpired, isTrialing } from '../../billing.js';

const emit = defineEmits(['connect', 'paywall']);

const recommended = computed(() => store.recommendedServer);
const expired = computed(() => isExpired());
const trialing = computed(() => isTrialing());

const status = computed(() => {
    if (expired.value) return 'expired';
    if (!recommended.value) return 'idle';
    return 'ready';
});

const heading = computed(() => {
    if (status.value === 'expired') return t('billing.expired');
    if (!recommended.value) return t('servers.empty');
    return recommended.value.name;
});

const subhead = computed(() => {
    if (status.value === 'expired') return t('billing.expiredBanner');
    if (!recommended.value) return '';
    const ping = recommended.value.pingMs;
    return ping ? `${Math.round(ping)} ${t('common.ms')}` : t('home.recommended');
});

function go() {
    hap.medium();
    if (status.value === 'expired') emit('paywall');
    else emit('connect');
}
</script>

<template>
    <BentoTile span="full" :rows="2" surface="aurora" interactive @click="go">
        <div class="connect-tile">
            <div class="connect-tile__top">
                <span class="connect-tile__eyebrow">{{ trialing ? t('billing.plans.trial_7d.name') : t('home.recommended') }}</span>
                <Zap :size="22" :stroke-width="1.75" />
            </div>
            <div class="connect-tile__body">
                <h2 class="connect-tile__h">{{ heading }}</h2>
                <p class="connect-tile__sub tabular-nums">{{ subhead }}</p>
            </div>
            <Button
                variant="primary"
                block
                @click.stop="go"
            >
                {{ status === 'expired' ? t('billing.renew') : t('server.connect') }}
            </Button>
        </div>
    </BentoTile>
</template>

<style scoped>
.connect-tile {
    display: flex;
    flex-direction: column;
    gap: 16px;
    height: 100%;
}
.connect-tile__top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    color: rgba(255, 255, 255, 0.85);
}
.connect-tile__eyebrow {
    font-size: var(--text-caption);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    font-weight: 600;
}
.connect-tile__body { flex: 1; display: flex; flex-direction: column; justify-content: center; gap: 6px; }
.connect-tile__h {
    font-family: var(--font-display);
    font-size: var(--text-display);
    line-height: var(--text-display--line-height);
    font-weight: 600;
    margin: 0;
    color: #fff;
}
.connect-tile__sub {
    margin: 0;
    color: rgba(255, 255, 255, 0.78);
    font-size: var(--text-body);
}
</style>
