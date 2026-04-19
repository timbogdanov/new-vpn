<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { t } from '../i18n.js';
import { hap } from '../telegram.js';
import { probeReachability } from '../composables/useProbe.js';
import { store, contributeSignals, fetchOoniSummary } from '../store.js';
import { SectionLabel, ListGroup, ListRow, Chip } from '../components/ui/index.js';

const route = useRoute();

const DEFAULT_DOMAINS = [
    { domain: 'instagram.com', key: 'instagram' },
    { domain: 'youtube.com',   key: 'youtube' },
    { domain: 'x.com',         key: 'x' },
    { domain: 'bbc.com',       key: 'bbc' },
    { domain: 'linkedin.com',  key: 'linkedin' },
    { domain: 'signal.org',    key: 'signal' },
    { domain: 'meduza.io',     key: 'meduza' },
    { domain: 'wikipedia.org', key: 'wikipedia' },
];

function extractHost(raw) {
    if (!raw) return null;
    try {
        const u = new URL(/^https?:\/\//i.test(raw) ? raw : `https://${raw}`);
        return u.hostname || null;
    } catch (_) { return null; }
}

// Single-URL override when deep-linked from FreedomUrlDetail's empty state.
const overrideHost = extractHost(route.query.url);
const DOMAINS = overrideHost
    ? [{ domain: overrideHost, key: '_custom' }]
    : DEFAULT_DOMAINS;

const results = ref(
    DOMAINS.reduce((acc, d) => { acc[d.domain] = 'checking'; return acc; }, {}),
);
const running = ref(false);

const total = DOMAINS.length;
const reachableCount = computed(() =>
    Object.values(results.value).filter((v) => v === 'reachable').length,
);
const summaryTone = computed(() => {
    if (running.value) return 'neutral';
    if (reachableCount.value === total) return 'success';
    if (reachableCount.value <= Math.floor(total / 2)) return 'danger';
    return 'warning';
});

function toneFor(state) {
    if (state === 'reachable') return 'success';
    if (state === 'blocked') return 'danger';
    return 'neutral';
}
function labelFor(state) {
    if (state === 'reachable') return t('tools.censorshipReachable');
    if (state === 'blocked') return t('tools.censorshipBlocked');
    return t('tools.censorshipChecking');
}

async function runAll() {
    if (running.value) return;
    running.value = true;
    hap.light();
    for (const d of DOMAINS) results.value[d.domain] = 'checking';
    const probed = await Promise.all(
        DOMAINS.map(async (d) => {
            const probeUrl = `https://${d.domain}/favicon.ico`;
            const state = await probeReachability(probeUrl, 5000);
            results.value[d.domain] = state;
            // Signal URL mirrors OONI's canonical web_connectivity input
            // (root path + trailing slash) so contributed data joins up with
            // the aggregation we read from OONI.
            const signalUrl = `https://${d.domain}/`;
            return { serviceKey: d.key, url: signalUrl, result: state };
        }),
    );
    running.value = false;

    // Voluntary contribute-back. Requires user opt-in AND a known country.
    if (store.user?.contributeSignals) {
        if (!store.ooni) { try { await fetchOoniSummary(); } catch (_) {} }
        const country = store.ooni?.countryCode;
        const asn = store.ooni?.asn || null;
        if (country) {
            const observedAt = new Date().toISOString();
            const signals = probed
                .filter((p) => p.result === 'reachable' || p.result === 'blocked')
                .map((p) => ({ ...p, observedAt }));
            if (signals.length) contributeSignals({ country, asn, signals });
        }
    }
}

onMounted(runAll);
</script>

<template>
    <div class="probe">
        <section class="probe__section">
            <SectionLabel>{{ t('tools.censorshipTitle') }}</SectionLabel>
            <p class="probe__intro">{{ t('tools.censorshipIntro') }}</p>

            <ListGroup>
                <ListRow :interactive="false">
                    <template #title>
                        {{ t('tools.censorshipSummary', { ok: reachableCount, total }) }}
                    </template>
                    <template #trailing>
                        <Chip :tone="summaryTone" dot>
                            {{ running
                                ? t('tools.censorshipRunning')
                                : t('tools.censorshipSummary', { ok: reachableCount, total }) }}
                        </Chip>
                    </template>
                </ListRow>

                <ListRow
                    v-for="d in DOMAINS"
                    :key="d.domain"
                    :interactive="false"
                >
                    <template #title>
                        <span class="probe__domain">{{ d.domain }}</span>
                    </template>
                    <template #trailing>
                        <Chip :tone="toneFor(results[d.domain])" dot>
                            {{ labelFor(results[d.domain]) }}
                        </Chip>
                    </template>
                </ListRow>

                <ListRow chevron :disabled="running" @click="runAll">
                    <template #title>
                        {{ running ? t('tools.censorshipRunning') : t('tools.censorshipRun') }}
                    </template>
                </ListRow>
            </ListGroup>
        </section>
    </div>
</template>

<style scoped>
.probe {
    display: flex;
    flex-direction: column;
    gap: 24px;
    padding-bottom: 32px;
}

.probe__section {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.probe__intro {
    margin: 0;
    padding: 0 16px 4px;
    font-size: 13px;
    line-height: 18px;
    color: var(--color-text-subtle);
}

.probe__domain {
    font-family: var(--font-mono);
    font-weight: 500;
    color: var(--color-text-strong);
}
</style>
