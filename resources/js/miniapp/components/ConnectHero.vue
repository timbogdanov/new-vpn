<script setup>
import { computed } from 'vue';
import FlagIcon from './FlagIcon.vue';
import { Chip, Button } from './ui/index.js';
import { t } from '../i18n.js';
import { hap } from '../telegram.js';

const props = defineProps({
    server: { type: Object, default: null },
    status: {
        type: String,
        default: 'idle',
        validator: (v) => ['idle', 'ready', 'connecting', 'connected', 'trial-ending', 'expired'].includes(v),
    },
    ping: { type: Number, default: null },
});

const emit = defineEmits(['connect', 'choose', 'paywall']);

const statusTone = computed(() => {
    switch (props.status) {
        case 'connected':    return 'accent';
        case 'trial-ending': return 'warning';
        case 'expired':      return 'danger';
        default:             return 'neutral';
    }
});

const statusLabel = computed(() => {
    switch (props.status) {
        case 'connected':    return t('server.connected');
        case 'connecting':   return t('server.connecting');
        case 'trial-ending': return t('billing.trialActive');
        case 'expired':      return t('billing.expired');
        case 'idle':         return t('home.noLastServer');
        default:             return t('home.recommended');
    }
});

const primaryLabel = computed(() => {
    if (props.status === 'connected')    return t('server.disconnect') || t('common.close');
    if (props.status === 'expired')      return t('billing.renew');
    if (props.status === 'trial-ending') return t('billing.upgrade');
    if (props.status === 'idle' || !props.server) return t('servers.title');
    return t('server.connect');
});

const loadLabel = computed(() => {
    const p = props.server?.loadPercent;
    if (p == null) return null;
    if (p >= 75) return t('server.loadBusy');
    if (p >= 40) return t('server.loadModerate');
    return t('server.loadLow');
});

function fire() {
    hap.medium();
    if (props.status === 'expired' || props.status === 'trial-ending') {
        emit('paywall');
        return;
    }
    if (!props.server || props.status === 'idle') {
        emit('choose');
        return;
    }
    emit('connect');
}
</script>

<template>
    <section class="hero">
        <div class="hero__status">
            <Chip :tone="statusTone" dot>{{ statusLabel }}</Chip>
        </div>

        <div class="hero__body">
            <FlagIcon v-if="server" :code="server.countryCode" :size="28" />
            <div class="hero__name">
                <h2 class="hero__title">{{ server ? server.name : t('servers.empty') }}</h2>
                <p v-if="server" class="hero__location">
                    {{ [server.city, server.country].filter(Boolean).join(', ') || '—' }}
                </p>
            </div>
        </div>

        <div v-if="server && (ping != null || loadLabel)" class="hero__meta">
            <span v-if="ping != null" class="hero__meta-value">{{ Math.round(ping) }}ms</span>
            <span v-if="ping != null && loadLabel" class="hero__meta-sep" aria-hidden="true">·</span>
            <span v-if="loadLabel" class="hero__meta-value">{{ loadLabel }}</span>
        </div>

        <div class="hero__action">
            <Button variant="primary" block @click="fire">
                {{ primaryLabel }}
            </Button>
        </div>
    </section>
</template>

<style scoped>
.hero {
    padding: 20px;
    background: var(--color-surface-raised);
    border-radius: var(--radius-lg);
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.hero__status {
    display: flex;
    align-items: center;
}

.hero__body {
    display: flex;
    align-items: center;
    gap: 12px;
}

.hero__name {
    flex: 1 1 auto;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.hero__title {
    margin: 0;
    font-size: 20px;
    line-height: 26px;
    font-weight: 600;
    letter-spacing: -0.01em;
    color: var(--color-text-strong);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.hero__location {
    margin: 0;
    font-size: 13px;
    line-height: 18px;
    color: var(--color-text-subtle);
}

.hero__meta {
    display: flex;
    align-items: center;
    gap: 8px;
    font-family: var(--font-mono);
    font-size: 13px;
    line-height: 18px;
    color: var(--color-text-subtle);
    font-variant-numeric: tabular-nums;
}
.hero__meta-sep { color: var(--color-text-hint); }

.hero__action { margin-top: 4px; }
</style>
