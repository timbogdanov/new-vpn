<script setup>
import { computed } from 'vue';
import { Card } from './ui/index.js';
import TimeseriesSparkline from './TimeseriesSparkline.vue';
import { t } from '../i18n.js';

const props = defineProps({
    aggregated: { type: Object, required: true },
    timeseries: { type: Array, required: true },
});

const hasData = computed(() => (props.aggregated?.totalChecks ?? 0) > 0);

const trendLabel = computed(() => {
    const trend = props.aggregated?.trendDirection;
    if (trend === 'worsening') return t('tools.freedomDetailSummaryTrendWorsening');
    if (trend === 'improving') return t('tools.freedomDetailSummaryTrendImproving');
    return t('tools.freedomDetailSummaryTrendSteady');
});
</script>

<template>
    <Card padding="lg" class="nsc">
        <p v-if="hasData" class="nsc__para">
            {{ t('tools.freedomDetailSummaryParagraph', {
                days: aggregated.windowDays,
                blocked: aggregated.blockedChecks,
                total: aggregated.totalChecks,
            }) }}
            <template v-if="aggregated.confirmedBlocks > 0">
                {{ t('tools.freedomDetailSummaryConfirmed', { n: aggregated.confirmedBlocks }) }}
            </template>
            {{ trendLabel }}
        </p>
        <p v-else class="nsc__para nsc__para--muted">
            {{ t('tools.freedomDetailSummaryNoData') }}
        </p>

        <div class="nsc__spark">
            <TimeseriesSparkline :points="timeseries" />
            <p class="nsc__caption">{{ t('tools.freedomDetailSparklineCaption') }}</p>
        </div>
    </Card>
</template>

<style scoped>
.nsc { display: flex; flex-direction: column; gap: 14px; }
.nsc__para {
    margin: 0;
    font-size: 15px;
    line-height: 22px;
    color: var(--color-text-strong);
}
.nsc__para--muted { color: var(--color-text-subtle); }
.nsc__spark { display: flex; flex-direction: column; gap: 4px; }
.nsc__caption {
    margin: 0;
    font-size: 11px;
    color: var(--color-text-hint);
    letter-spacing: 0.04em;
}
</style>
