<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';

import { store, fetchOoniSummary, fetchOoniWatchlist, toggleOoniWatch } from '../store.js';
import { t } from '../i18n.js';
import { hap } from '../telegram.js';

import { SectionLabel, ListGroup, ListRow, Chip } from '../components/ui/index.js';
import Skeleton from '../components/Skeleton.vue';

const router = useRouter();
const err = ref(null);

async function load({ force = false } = {}) {
    err.value = null;
    try { await fetchOoniSummary({ force }); }
    catch (e) { err.value = e?.response?.data?.message || e?.message || 'load_failed'; }
}

onMounted(() => {
    load();
    fetchOoniWatchlist();
});

const watchedSet = computed(() => new Set(store.ooniWatchlist || []));
function isWatched(key) { return watchedSet.value.has(key); }

async function onToggleWatch(svc, e) {
    e?.stopPropagation();
    hap.select();
    try { await toggleOoniWatch(svc.key); }
    catch (_) { /* store handles rollback */ }
}

const summary = computed(() => store.ooni);
const loading = computed(() => store.ooniLoading);
const services = computed(() => summary.value?.services || []);
const blocked = computed(() => services.value.filter((s) => s.status === 'blocked' || s.status === 'degraded'));
const total = computed(() => services.value.length);

const asnLabel = computed(() => {
    const s = summary.value;
    if (!s) return '';
    const name = s.asnName ? s.asnName : '';
    if (s.asn && name) return `${name} · ${s.asn}`;
    if (s.asn) return s.asn;
    if (name) return name;
    return '';
});

const overallTone = computed(() => {
    if (!summary.value || !total.value) return 'neutral';
    const n = blocked.value.length;
    if (n === 0) return 'success';
    if (n <= Math.ceil(total.value * 0.2)) return 'warning';
    return 'danger';
});

function toneFor(status) {
    if (status === 'reachable') return 'success';
    if (status === 'degraded') return 'warning';
    if (status === 'blocked') return 'danger';
    return 'neutral';
}
function labelFor(status) {
    if (status === 'reachable') return t('tools.freedomReachable');
    if (status === 'degraded') return t('tools.freedomDegraded');
    if (status === 'blocked') return t('tools.freedomBlocked');
    return t('tools.freedomUnknown');
}

function unblock(svc) {
    hap.select();
    const recommended = store.recommendedServer;
    if (recommended) {
        router.push(`/servers/${recommended.slug}`);
        return;
    }
    router.push('/servers');
}

function refresh() {
    hap.light();
    load({ force: true });
}
</script>

<template>
    <div class="freedom">
        <section class="freedom__section">
            <SectionLabel>{{ t('tools.freedomTitle') }}</SectionLabel>

            <p v-if="summary" class="freedom__intro">
                {{ t('tools.freedomIntro', {
                    country: summary.countryCode || '—',
                    asn: asnLabel || t('tools.freedomAsnUnknown'),
                }) }}
            </p>
            <p v-else class="freedom__intro">
                {{ t('tools.freedomDetecting') }}
            </p>

            <div v-if="loading && !summary" class="freedom__skel">
                <Skeleton v-for="i in 6" :key="i" :height="56" />
            </div>

            <template v-else-if="summary">
                <ListGroup>
                    <ListRow :interactive="false">
                        <template #title>
                            {{ t('tools.freedomSummary', { blocked: blocked.length, total }) }}
                        </template>
                        <template #subtitle>
                            {{ t('tools.freedomLookback', { days: summary.lookbackDays || 7 }) }}
                        </template>
                        <template #trailing>
                            <Chip :tone="overallTone" dot>
                                {{ blocked.length }}/{{ total }}
                            </Chip>
                        </template>
                    </ListRow>

                    <ListRow
                        v-for="svc in services"
                        :key="svc.key"
                        :chevron="svc.status === 'blocked' || svc.status === 'degraded'"
                        :interactive="svc.status === 'blocked' || svc.status === 'degraded'"
                        @click="(svc.status === 'blocked' || svc.status === 'degraded') ? unblock(svc) : null"
                    >
                        <template #title>{{ svc.label }}</template>
                        <template #subtitle>
                            <span v-if="svc.status === 'unknown' && !svc.communityMeasurements">
                                {{ t('tools.freedomUnknownSub') }}
                            </span>
                            <span v-else-if="svc.communityMeasurements">
                                {{ t('tools.freedomCommunityReports', { n: svc.communityMeasurements }) }}
                            </span>
                            <span v-else-if="svc.measurements">
                                {{ t('tools.freedomMeasurements', { n: svc.measurements }) }}
                            </span>
                        </template>
                        <template #trailing>
                            <button
                                type="button"
                                class="freedom__watch"
                                :class="{ 'freedom__watch--on': isWatched(svc.key) }"
                                :aria-pressed="isWatched(svc.key)"
                                @click="onToggleWatch(svc, $event)"
                            >
                                {{ isWatched(svc.key) ? t('tools.freedomWatching') : t('tools.freedomWatch') }}
                            </button>
                            <Chip :tone="toneFor(svc.status)" dot>
                                {{ labelFor(svc.status) }}
                            </Chip>
                        </template>
                    </ListRow>

                    <ListRow chevron :disabled="loading" @click="refresh">
                        <template #title>
                            {{ loading ? t('tools.freedomRunning') : t('tools.freedomRefresh') }}
                        </template>
                    </ListRow>
                </ListGroup>
            </template>

            <p v-if="err" class="freedom__err">{{ err }}</p>

            <p class="freedom__attribution">
                {{ t('tools.freedomAttribution') }}
            </p>
        </section>
    </div>
</template>

<style scoped>
.freedom {
    display: flex;
    flex-direction: column;
    gap: 24px;
    padding-bottom: 32px;
}

.freedom__section {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.freedom__intro {
    margin: 0;
    padding: 0 16px 4px;
    font-size: 13px;
    line-height: 18px;
    color: var(--color-text-subtle);
}

.freedom__skel {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.freedom__err {
    margin: 0;
    padding: 0 16px;
    font-size: 12px;
    line-height: 16px;
    color: var(--color-danger);
}

.freedom__watch {
    font-size: 11px;
    line-height: 14px;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    padding: 3px 10px;
    border-radius: var(--radius-pill);
    background: color-mix(in srgb, var(--color-text-subtle) 10%, transparent);
    color: var(--color-text-subtle);
    transition: background var(--duration-fast) var(--ease-standard), color var(--duration-fast) var(--ease-standard);
}
.freedom__watch--on {
    background: var(--color-accent-tint);
    color: var(--color-accent);
}
.freedom__watch:active {
    background: color-mix(in srgb, var(--color-text-subtle) 20%, transparent);
}

.freedom__attribution {
    margin: 4px 0 0;
    padding: 0 16px;
    font-size: 11px;
    line-height: 14px;
    color: var(--color-text-hint);
    letter-spacing: 0.04em;
}
</style>
