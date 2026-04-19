<script setup>
import { computed } from 'vue';
import { Card, Chip } from './ui/index.js';
import { t } from '../i18n.js';

const props = defineProps({
    host: { type: String, required: true },
    status: { type: String, required: true },
    reason: { type: String, default: '' },
    countryCode: { type: String, default: '' },
    asn: { type: String, default: '' },
    asnName: { type: String, default: '' },
    freshAt: { type: String, default: '' },
    degradedConfidence: { type: Boolean, default: false },
    communityCount: { type: Number, default: 0 },
});

const tone = computed(() => {
    if (props.status === 'blocked') return 'danger';
    if (props.status === 'degraded') return 'warning';
    if (props.status === 'reachable') return 'success';
    return 'neutral';
});

const titleKey = computed(() => {
    const map = {
        blocked: 'tools.freedomDetailVerdictBlocked',
        degraded: 'tools.freedomDetailVerdictDegraded',
        reachable: 'tools.freedomDetailVerdictReachable',
        unknown: 'tools.freedomDetailVerdictUnknown',
    };
    return map[props.status] || map.unknown;
});

const reasonKey = computed(() => {
    const map = {
        confirmed_block: 'tools.freedomDetailReasonConfirmedBlock',
        high_anomaly: 'tools.freedomDetailReasonHighAnomaly',
        partial_anomaly: 'tools.freedomDetailReasonPartialAnomaly',
        no_data: 'tools.freedomDetailReasonNoData',
        community_only: 'tools.freedomDetailReasonCommunityOnly',
        reachable_strong: 'tools.freedomDetailReasonReachableStrong',
    };
    return map[props.reason] || '';
});

const networkLabel = computed(() => {
    const parts = [];
    if (props.countryCode) parts.push(props.countryCode);
    if (props.asnName) parts.push(props.asnName);
    else if (props.asn) parts.push(props.asn);
    return parts.join(' · ');
});

const relativeFresh = computed(() => {
    if (!props.freshAt) return '';
    const delta = Date.now() - Date.parse(props.freshAt);
    if (Number.isNaN(delta)) return '';
    const mins = Math.max(1, Math.round(delta / 60000));
    if (mins < 60) return `${mins}m ago`;
    const hrs = Math.round(mins / 60);
    if (hrs < 24) return `${hrs}h ago`;
    return `${Math.round(hrs / 24)}d ago`;
});
</script>

<template>
    <Card padding="lg" class="verdict-hero">
        <div class="verdict-hero__head">
            <Chip :tone="tone" dot>{{ status.toUpperCase() }}</Chip>
            <span v-if="networkLabel" class="verdict-hero__net">{{ networkLabel }}</span>
        </div>
        <h2 class="verdict-hero__title">{{ t(titleKey, { host }) }}</h2>
        <p v-if="reasonKey" class="verdict-hero__reason">{{ t(reasonKey) }}</p>
        <p v-if="degradedConfidence" class="verdict-hero__note">{{ t('tools.freedomDetailDegradedConfidence') }}</p>
        <div class="verdict-hero__meta">
            <span v-if="relativeFresh">{{ t('tools.freedomDetailFresh', { time: relativeFresh }) }}</span>
            <span v-if="communityCount > 0">{{ t('tools.freedomDetailCommunity', { n: communityCount }) }}</span>
        </div>
    </Card>
</template>

<style scoped>
.verdict-hero { display: flex; flex-direction: column; gap: 10px; }
.verdict-hero__head {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
}
.verdict-hero__net {
    font-size: 11px;
    color: var(--color-text-hint);
    letter-spacing: 0.06em;
    text-transform: uppercase;
}
.verdict-hero__title {
    font-family: var(--font-serif, 'Instrument Serif', Georgia, serif);
    font-weight: 400;
    font-size: 26px;
    line-height: 32px;
    margin: 4px 0 0;
    color: var(--color-text-strong);
}
.verdict-hero__reason {
    margin: 0;
    font-size: 14px;
    line-height: 20px;
    color: var(--color-text-subtle);
}
.verdict-hero__note {
    margin: 4px 0 0;
    font-size: 12px;
    line-height: 16px;
    color: var(--color-warning);
}
.verdict-hero__meta {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    margin-top: 4px;
    font-size: 11px;
    color: var(--color-text-hint);
    letter-spacing: 0.04em;
}
</style>
