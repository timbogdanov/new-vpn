<script setup>
import { computed } from 'vue';
import { ShieldCheck, Network, Activity, Radio } from 'lucide-vue-next';

import { store } from '../store.js';
import { t } from '../i18n.js';

const props = defineProps({
    server: { type: Object, required: true },
});

const clientForServer = computed(() => {
    const slug = props.server?.slug;
    if (!slug) return null;
    return store.profile?.clients?.find((c) => c.server?.slug === slug) || null;
});

const trafficLabel = computed(() => {
    const total = clientForServer.value?.traffic?.total ?? 0;
    if (!total) return t('server.yourTrafficEmpty');
    return formatBytes(total);
});

const endpointLabel = computed(() => (
    props.server?.hostPreview || t('server.endpointUnknown')
));

function formatBytes(bytes) {
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let i = 0; let n = bytes;
    while (n >= 1024 && i < units.length - 1) { n /= 1024; i++; }
    return `${n.toFixed(n >= 100 || i === 0 ? 0 : 1)} ${units[i]}`;
}
</script>

<template>
    <section class="facts">
        <div class="facts__tile">
            <div class="facts__icon"><Radio :size="16" :stroke-width="1.75" /></div>
            <div class="facts__label">{{ t('server.protocol') }}</div>
            <div class="facts__value">{{ t('server.protocolValue') }}</div>
        </div>

        <div class="facts__tile">
            <div class="facts__icon"><Network :size="16" :stroke-width="1.75" /></div>
            <div class="facts__label">{{ t('server.endpoint') }}</div>
            <div class="facts__value facts__value--mono">{{ endpointLabel }}</div>
        </div>

        <div class="facts__tile">
            <div class="facts__icon"><Activity :size="16" :stroke-width="1.75" /></div>
            <div class="facts__label">{{ t('server.yourTraffic') }}</div>
            <div class="facts__value tabular-nums">{{ trafficLabel }}</div>
        </div>

        <div class="facts__tile">
            <div class="facts__icon"><ShieldCheck :size="16" :stroke-width="1.75" /></div>
            <div class="facts__label">{{ t('server.protectedBy') }}</div>
            <div class="facts__value">{{ t('server.protectedByValue') }}</div>
        </div>
    </section>
</template>

<style scoped>
.facts {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.facts__tile {
    position: relative;
    padding: 14px 14px 12px;
    border-radius: var(--radius-md);
    background: var(--color-surface);
    border: 1px solid var(--color-separator);
    display: flex;
    flex-direction: column;
    gap: 6px;
    min-width: 0;
}

.facts__icon {
    width: 28px;
    height: 28px;
    border-radius: var(--radius-pill);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--color-accent);
    background: color-mix(in srgb, var(--color-accent) 12%, transparent);
}

.facts__label {
    font-size: 11px;
    line-height: 14px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--color-text-hint);
    font-weight: 600;
}

.facts__value {
    font-size: 14px;
    line-height: 18px;
    font-weight: 600;
    color: var(--color-text);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.facts__value--mono {
    font-family: var(--font-mono);
    font-weight: 500;
    font-size: 12px;
    letter-spacing: -0.01em;
    color: var(--color-text-subtle);
}
</style>
