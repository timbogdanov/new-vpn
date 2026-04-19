<script setup>
import { ListGroup, ListRow, Chip } from './ui/index.js';
import { t } from '../i18n.js';

defineProps({
    breakdown: { type: Array, default: () => [] },
});

function tone(status) {
    if (status === 'reachable') return 'success';
    if (status === 'degraded') return 'warning';
    if (status === 'blocked') return 'danger';
    return 'neutral';
}

function label(status) {
    if (status === 'reachable') return t('tools.freedomReachable');
    if (status === 'degraded') return t('tools.freedomDegraded');
    if (status === 'blocked') return t('tools.freedomBlocked');
    return t('tools.freedomUnknown');
}
</script>

<template>
    <div>
        <ListGroup v-if="breakdown && breakdown.length">
            <ListRow
                v-for="row in breakdown"
                :key="row.asn"
                :interactive="false"
            >
                <template #title>{{ row.asnName || row.asn }}</template>
                <template #subtitle>{{ t('tools.freedomMeasurements', { n: row.measurements }) }}</template>
                <template #trailing>
                    <Chip :tone="tone(row.status)" dot>{{ label(row.status) }}</Chip>
                </template>
            </ListRow>
        </ListGroup>
        <p v-else class="asn-breakdown__empty">{{ t('tools.freedomDetailNoAsnBreakdown') }}</p>
    </div>
</template>

<style scoped>
.asn-breakdown__empty {
    margin: 0;
    padding: 0 16px;
    font-size: 12px;
    color: var(--color-text-hint);
}
</style>
