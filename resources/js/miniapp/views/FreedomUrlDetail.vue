<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import { store, fetchUrlDetails, toggleUrlWatch } from '../store.js';
import { t } from '../i18n.js';
import { hap } from '../telegram.js';

import { SectionLabel, Button, Sheet, ListRow, ListGroup } from '../components/ui/index.js';
import Skeleton from '../components/Skeleton.vue';
import VerdictHero from '../components/VerdictHero.vue';
import TimeseriesSparkline from '../components/TimeseriesSparkline.vue';
import AsnBreakdownList from '../components/AsnBreakdownList.vue';
import MeasurementList from '../components/MeasurementList.vue';

const props = defineProps({ urlHash: { type: String, required: true } });
const route = useRoute();
const router = useRouter();

const url = computed(() => {
    const q = route.query.url;
    return typeof q === 'string' ? q : '';
});

const details = ref(null);
const loading = ref(false);
const err = ref(null);
const showWhy = ref(false);

async function load({ force = false } = {}) {
    err.value = null;
    if (!url.value) { err.value = 'missing_url'; return; }
    loading.value = true;
    try {
        const data = await fetchUrlDetails(url.value, { force });
        details.value = data;
    } catch (e) {
        err.value = e?.response?.data?.message || e?.message || 'load_failed';
    } finally {
        loading.value = false;
    }
}

onMounted(load);
watch(() => route.query.url, () => load());

const isWatched = computed(() => (store.ooniWatchlistUrls || []).includes(details.value?.url || url.value));

async function onToggleWatch() {
    const target = details.value?.url || url.value;
    if (!target) return;
    hap.select();
    try { await toggleUrlWatch(target); } catch (_) {}
}

function onUnblock() {
    hap.select();
    const slug = details.value?.recommendedServerSlug;
    if (slug) router.push(`/servers/${slug}`);
    else router.push('/servers');
}

function onProbe() {
    hap.select();
    router.push({ name: 'censorship', query: { url: url.value } });
}

function onRecheck() {
    hap.light();
    load({ force: true });
}
</script>

<template>
    <div class="fud">
        <div v-if="loading && !details" class="fud__skel">
            <Skeleton :height="120" />
            <Skeleton :height="80" />
            <Skeleton :height="200" />
        </div>

        <template v-else-if="details">
            <VerdictHero
                :host="details.host"
                :status="details.verdictStatus"
                :reason="details.verdictReason"
                :country-code="details.countryCode"
                :asn="details.asn"
                :asn-name="details.asnName"
                :fresh-at="details.freshAt"
                :degraded-confidence="details.degradedConfidence"
                :community-count="details.communityCount"
            />

            <section class="fud__section">
                <SectionLabel>
                    {{ t('tools.freedomDetailTimeseries', { days: details.lookbackDays }) }}
                </SectionLabel>
                <TimeseriesSparkline :points="details.timeseries" />
            </section>

            <section class="fud__section">
                <SectionLabel>
                    {{ t('tools.freedomDetailAsnBreakdown', { country: details.countryCode }) }}
                </SectionLabel>
                <AsnBreakdownList :breakdown="details.asnBreakdown" />
            </section>

            <section class="fud__section">
                <SectionLabel>{{ t('tools.freedomDetailMeasurements') }}</SectionLabel>
                <MeasurementList :measurements="details.recentMeasurements" />
            </section>

            <section class="fud__actions">
                <Button
                    v-if="details.verdictStatus === 'blocked' || details.verdictStatus === 'degraded'"
                    variant="primary"
                    @click="onUnblock"
                >
                    {{ t('tools.freedomDetailUnblockCta') }}
                </Button>

                <ListGroup>
                    <ListRow chevron @click="onToggleWatch">
                        <template #title>
                            {{ isWatched ? t('tools.freedomWatching') : t('tools.freedomWatch') }}
                        </template>
                    </ListRow>
                    <ListRow v-if="!details.measurements || !details.recentMeasurements?.length" chevron @click="onProbe">
                        <template #title>{{ t('tools.freedomDetailRunProbe') }}</template>
                    </ListRow>
                    <ListRow chevron @click="onRecheck" :disabled="loading">
                        <template #title>
                            {{ loading ? t('tools.freedomRunning') : t('tools.freedomDetailRecheck') }}
                        </template>
                    </ListRow>
                    <ListRow chevron @click="showWhy = true">
                        <template #title>{{ t('tools.freedomDetailWhyTitle') }}</template>
                    </ListRow>
                </ListGroup>

                <p class="fud__attribution">{{ t('tools.freedomAttribution') }}</p>
            </section>
        </template>

        <p v-if="err && !details" class="fud__err">{{ err }}</p>

        <Sheet v-model:open="showWhy" :aria-label="t('tools.freedomDetailWhyTitle')">
            <h3 class="fud__why-title">{{ t('tools.freedomDetailWhyTitle') }}</h3>
            <p class="fud__why-body">{{ t('tools.freedomDetailWhyBody') }}</p>
        </Sheet>
    </div>
</template>

<style scoped>
.fud {
    display: flex;
    flex-direction: column;
    gap: 20px;
    padding: 16px 16px 32px;
}
.fud__skel { display: flex; flex-direction: column; gap: 12px; }
.fud__section { display: flex; flex-direction: column; gap: 8px; }
.fud__actions { display: flex; flex-direction: column; gap: 12px; }
.fud__err {
    margin: 0;
    padding: 0 16px;
    font-size: 12px;
    color: var(--color-danger);
}
.fud__attribution {
    margin: 4px 0 0;
    font-size: 11px;
    color: var(--color-text-hint);
    letter-spacing: 0.04em;
    text-align: center;
}
.fud__why-title {
    margin: 0 0 8px;
    font-family: var(--font-serif, 'Instrument Serif', Georgia, serif);
    font-weight: 400;
    font-size: 22px;
    line-height: 28px;
    color: var(--color-text-strong);
}
.fud__why-body {
    margin: 0;
    font-size: 14px;
    line-height: 20px;
    color: var(--color-text-subtle);
}
</style>
