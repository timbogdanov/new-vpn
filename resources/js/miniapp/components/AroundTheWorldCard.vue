<script setup>
import { computed } from 'vue';
import { SectionLabel, ListGroup, ListRow, Chip } from './ui/index.js';
import { t } from '../i18n.js';
import { i18nState } from '../i18n.js';

const props = defineProps({
    breakdown: { type: Array, default: () => [] },
});

const regional = computed(() => props.breakdown.filter((r) => r.isRegional));
const worst = computed(() => props.breakdown.filter((r) => !r.isRegional));

// Intl.DisplayNames gives us localized country names for free. Falls back to
// the country code if the runtime doesn't support it (very old browsers).
let displayNames = null;
try {
    const locale = i18nState.locale || 'en';
    displayNames = new Intl.DisplayNames([locale], { type: 'region' });
} catch (_) { displayNames = null; }

function countryName(cc) {
    try { return displayNames?.of(cc) || cc; } catch (_) { return cc; }
}

function flag(cc) {
    if (!cc || cc.length !== 2) return '';
    const A = 0x1F1E6;
    const a = 'A'.charCodeAt(0);
    return String.fromCodePoint(A + cc.charCodeAt(0) - a, A + cc.charCodeAt(1) - a);
}

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
</script>

<template>
    <section class="atw" v-if="breakdown && breakdown.length">
        <SectionLabel>{{ t('tools.freedomDetailAroundTheWorld') }}</SectionLabel>

        <template v-if="regional.length">
            <p class="atw__sub">{{ t('tools.freedomDetailNearbyCountries') }}</p>
            <ListGroup>
                <ListRow v-for="row in regional" :key="`r-${row.countryCode}`" :interactive="false">
                    <template #title>
                        <span class="atw__flag">{{ flag(row.countryCode) }}</span>
                        {{ countryName(row.countryCode) }}
                    </template>
                    <template #trailing>
                        <Chip :tone="tone(row.status)" dot>{{ statusLabel(row.status) }}</Chip>
                    </template>
                </ListRow>
            </ListGroup>
        </template>

        <template v-if="worst.length">
            <p class="atw__sub">{{ t('tools.freedomDetailWorstElsewhere') }}</p>
            <ListGroup>
                <ListRow v-for="row in worst" :key="`w-${row.countryCode}`" :interactive="false">
                    <template #title>
                        <span class="atw__flag">{{ flag(row.countryCode) }}</span>
                        {{ countryName(row.countryCode) }}
                    </template>
                    <template #trailing>
                        <Chip :tone="tone(row.status)" dot>{{ statusLabel(row.status) }}</Chip>
                    </template>
                </ListRow>
            </ListGroup>
        </template>
    </section>
</template>

<style scoped>
.atw { display: flex; flex-direction: column; gap: 8px; }
.atw__sub {
    margin: 8px 16px 2px;
    font-size: 11px;
    letter-spacing: 0.06em;
    text-transform: uppercase;
    color: var(--color-text-hint);
}
.atw__flag {
    display: inline-block;
    margin-right: 6px;
    font-size: 18px;
    line-height: 1;
    vertical-align: -2px;
}
</style>
