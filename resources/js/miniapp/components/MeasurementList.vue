<script setup>
import { ListGroup, ListRow } from './ui/index.js';
import { t } from '../i18n.js';
import { openExternal } from '../telegram.js';

defineProps({
    measurements: { type: Array, default: () => [] },
});

function relTime(iso) {
    if (!iso) return '';
    const t = Date.parse(iso);
    if (Number.isNaN(t)) return iso;
    const delta = Math.max(0, Date.now() - t);
    const m = Math.round(delta / 60000);
    if (m < 60) return `${m}m ago`;
    const h = Math.round(m / 60);
    if (h < 24) return `${h}h ago`;
    return `${Math.round(h / 24)}d ago`;
}

function onRowClick(row) {
    const url = row?.measurementUrl;
    if (!url) return;
    try { openExternal(url); } catch (_) { window.open(url, '_blank'); }
}
</script>

<template>
    <div>
        <ListGroup v-if="measurements && measurements.length">
            <ListRow
                v-for="m in measurements"
                :key="m.measurementUid || m.reportId || `${m.probeAsn}-${m.measurementStartTime}`"
                :chevron="!!m.measurementUrl"
                :interactive="!!m.measurementUrl"
                @click="onRowClick(m)"
            >
                <template #title>{{ m.probeAsn || '—' }}</template>
                <template #subtitle>{{ relTime(m.measurementStartTime) }}</template>
                <template #trailing>
                    <span class="m-flags">
                        <span v-if="m.confirmed" class="m-flag m-flag--danger">C</span>
                        <span v-else-if="m.anomaly" class="m-flag m-flag--warning">A</span>
                        <span v-else-if="m.failure" class="m-flag m-flag--neutral">F</span>
                        <span v-else class="m-flag m-flag--success">OK</span>
                    </span>
                </template>
            </ListRow>
        </ListGroup>
        <p v-else class="ml-empty">{{ t('tools.freedomDetailNoMeasurements') }}</p>
    </div>
</template>

<style scoped>
.m-flags { display: inline-flex; gap: 4px; }
.m-flag {
    min-width: 26px;
    padding: 2px 6px;
    border-radius: var(--radius-sm);
    font-size: 10px;
    font-weight: 700;
    text-align: center;
    letter-spacing: 0.06em;
}
.m-flag--danger  { background: color-mix(in srgb, var(--color-danger) 14%, transparent); color: var(--color-danger); }
.m-flag--warning { background: color-mix(in srgb, var(--color-warning) 14%, transparent); color: var(--color-warning); }
.m-flag--success { background: color-mix(in srgb, var(--color-success) 14%, transparent); color: var(--color-success); }
.m-flag--neutral { background: color-mix(in srgb, var(--color-text-subtle) 10%, transparent); color: var(--color-text-subtle); }

.ml-empty {
    margin: 0;
    padding: 0 16px;
    font-size: 12px;
    color: var(--color-text-hint);
}
</style>
