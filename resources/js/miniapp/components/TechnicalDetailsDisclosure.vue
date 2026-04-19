<script setup>
import { ref } from 'vue';
import { Card, ListGroup, ListRow } from './ui/index.js';
import MeasurementList from './MeasurementList.vue';
import { t } from '../i18n.js';
import { openExternal } from '../telegram.js';

const props = defineProps({
    details: { type: Object, required: true },
});

const open = ref(false);

function onOpenExplorer() {
    const url = props.details?.url;
    const cc = props.details?.countryCode;
    if (!url) return;
    const target = `https://explorer.ooni.org/search?test_name=web_connectivity${cc ? `&probe_cc=${cc}` : ''}&input=${encodeURIComponent(url)}`;
    try { openExternal(target); } catch (_) { window.open(target, '_blank'); }
}
</script>

<template>
    <section class="tdd">
        <button type="button" class="tdd__toggle" :aria-expanded="open" @click="open = !open">
            {{ open ? t('tools.freedomDetailHideTechnical') : t('tools.freedomDetailTechnicalDetails') }}
            <svg class="tdd__caret" :class="{ 'tdd__caret--open': open }" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="6 9 12 15 18 9" /></svg>
        </button>

        <div v-if="open" class="tdd__body">
            <Card padding="md">
                <dl class="tdd__dl">
                    <div><dt>{{ t('tools.freedomDetailRawChecks') }}</dt><dd>{{ details.measurements }}</dd></div>
                    <div><dt>{{ t('tools.freedomDetailRawOk') }}</dt><dd>{{ details.ok }}</dd></div>
                    <div><dt>{{ t('tools.freedomDetailRawAnomaly') }}</dt><dd>{{ details.anomaly }}</dd></div>
                    <div><dt>{{ t('tools.freedomDetailRawConfirmed') }}</dt><dd>{{ details.confirmed }}</dd></div>
                    <div><dt>{{ t('tools.freedomDetailRawFailure') }}</dt><dd>{{ details.failure }}</dd></div>
                    <div><dt>{{ t('tools.freedomDetailRawReason') }}</dt><dd>{{ details.verdictReason || '—' }}</dd></div>
                </dl>
            </Card>

            <div v-if="details.recentMeasurements?.length">
                <p class="tdd__label">{{ t('tools.freedomDetailMeasurements') }}</p>
                <MeasurementList :measurements="details.recentMeasurements" />
            </div>

            <ListGroup>
                <ListRow chevron @click="onOpenExplorer">
                    <template #title>{{ t('tools.freedomDetailOpenOoni') }}</template>
                </ListRow>
            </ListGroup>
        </div>
    </section>
</template>

<style scoped>
.tdd { display: flex; flex-direction: column; gap: 10px; }
.tdd__toggle {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: transparent;
    color: var(--color-text-subtle);
    font-size: 12px;
    letter-spacing: 0.04em;
    padding: 6px 16px;
    border: 0;
    align-self: flex-start;
}
.tdd__caret { transition: transform var(--duration-fast) var(--ease-standard); }
.tdd__caret--open { transform: rotate(180deg); }
.tdd__body { display: flex; flex-direction: column; gap: 12px; padding: 0 16px; }
.tdd__label {
    margin: 12px 0 6px;
    font-size: 11px;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--color-text-hint);
}
.tdd__dl {
    margin: 0;
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 6px 16px;
    font-variant-numeric: tabular-nums;
}
.tdd__dl > div { display: contents; }
.tdd__dl dt { color: var(--color-text-subtle); font-size: 13px; margin: 0; }
.tdd__dl dd { color: var(--color-text-strong); font-size: 13px; margin: 0; }
</style>
