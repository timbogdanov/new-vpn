<script setup>
import { ref, computed } from 'vue';
import { SectionLabel, ListGroup, ListRow, Chip, Sheet } from './ui/index.js';
import { t } from '../i18n.js';

defineProps({
    breakdown: { type: Array, default: () => [] },
});

const openRow = ref(null);
const sheetOpen = computed({
    get: () => openRow.value !== null,
    set: (v) => { if (!v) openRow.value = null; },
});

function tone(status) {
    if (status === 'reachable') return 'success';
    if (status === 'degraded') return 'warning';
    if (status === 'blocked') return 'danger';
    return 'neutral';
}

function statusLabel(status) {
    if (status === 'reachable') return t('tools.freedomDetailStatusReachable');
    if (status === 'degraded') return t('tools.freedomDetailStatusDegraded');
    if (status === 'blocked') return t('tools.freedomDetailStatusBlocked');
    return t('tools.freedomDetailStatusUnknown');
}

function networkTypeLabel(networkType) {
    if (networkType === 'mobile') return t('tools.freedomDetailNetworkMobile');
    if (networkType === 'broadband') return t('tools.freedomDetailNetworkBroadband');
    if (networkType === 'cdn') return t('tools.freedomDetailNetworkCdn');
    if (networkType === 'cloud') return t('tools.freedomDetailNetworkCloud');
    if (networkType === 'transit') return t('tools.freedomDetailNetworkTransit');
    return t('tools.freedomDetailNetworkUnknown');
}

function titleFor(row) {
    return row.friendlyName || row.asnName || row.asn;
}
</script>

<template>
    <section class="nnc">
        <SectionLabel>{{ t('tools.freedomDetailNearbyNetworks') }}</SectionLabel>
        <ListGroup v-if="breakdown && breakdown.length">
            <ListRow
                v-for="row in breakdown"
                :key="row.asn"
                chevron
                @click="openRow = row"
            >
                <template #title>{{ titleFor(row) }}</template>
                <template #subtitle>{{ networkTypeLabel(row.networkType) }}</template>
                <template #trailing>
                    <Chip :tone="tone(row.status)" dot>{{ statusLabel(row.status) }}</Chip>
                </template>
            </ListRow>
        </ListGroup>
        <p v-else class="nnc__empty">{{ t('tools.freedomDetailNoAsnBreakdown') }}</p>

        <Sheet v-model:open="sheetOpen">
            <template v-if="openRow">
                <h3 class="nnc__detail-title">{{ titleFor(openRow) }}</h3>
                <p class="nnc__detail-sub">{{ networkTypeLabel(openRow.networkType) }} · {{ openRow.asn }}</p>
                <dl class="nnc__dl">
                    <div><dt>{{ t('tools.freedomDetailRawChecks') }}</dt><dd>{{ openRow.measurements }}</dd></div>
                    <div><dt>{{ t('tools.freedomDetailRawOk') }}</dt><dd>{{ openRow.ok }}</dd></div>
                    <div><dt>{{ t('tools.freedomDetailRawAnomaly') }}</dt><dd>{{ openRow.anomaly }}</dd></div>
                    <div><dt>{{ t('tools.freedomDetailRawConfirmed') }}</dt><dd>{{ openRow.confirmed }}</dd></div>
                    <div><dt>{{ t('tools.freedomDetailRawFailure') }}</dt><dd>{{ openRow.failure }}</dd></div>
                </dl>
            </template>
        </Sheet>
    </section>
</template>

<style scoped>
.nnc { display: flex; flex-direction: column; gap: 8px; }
.nnc__empty {
    margin: 0;
    padding: 0 16px;
    font-size: 12px;
    color: var(--color-text-hint);
}
.nnc__detail-title {
    margin: 0 0 4px;
    font-family: var(--font-serif, 'Instrument Serif', Georgia, serif);
    font-weight: 400;
    font-size: 22px;
    line-height: 28px;
    color: var(--color-text-strong);
}
.nnc__detail-sub {
    margin: 0 0 16px;
    font-size: 13px;
    color: var(--color-text-subtle);
}
.nnc__dl {
    margin: 0;
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 8px 16px;
    font-variant-numeric: tabular-nums;
}
.nnc__dl > div { display: contents; }
.nnc__dl dt { color: var(--color-text-subtle); font-size: 13px; margin: 0; }
.nnc__dl dd { color: var(--color-text-strong); font-size: 13px; margin: 0; }
</style>
