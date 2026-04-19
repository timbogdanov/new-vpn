<script setup>
import { computed, onMounted, ref } from 'vue';
import { t } from '../i18n.js';
import { hap } from '../telegram.js';
import { probeReachability } from '../composables/useProbe.js';
import { SectionLabel, ListGroup, ListRow, Chip } from '../components/ui/index.js';

const DOMAINS = [
    'instagram.com',
    'youtube.com',
    'x.com',
    'bbc.com',
    'linkedin.com',
    'signal.org',
    'meduza.io',
    'wikipedia.org',
];

const results = ref(
    DOMAINS.reduce((acc, d) => { acc[d] = 'checking'; return acc; }, {}),
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
    for (const d of DOMAINS) results.value[d] = 'checking';
    await Promise.all(
        DOMAINS.map(async (d) => {
            const state = await probeReachability(`https://${d}/favicon.ico`, 5000);
            results.value[d] = state;
        }),
    );
    running.value = false;
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
                    :key="d"
                    :interactive="false"
                >
                    <template #title>
                        <span class="probe__domain">{{ d }}</span>
                    </template>
                    <template #trailing>
                        <Chip :tone="toneFor(results[d])" dot>
                            {{ labelFor(results[d]) }}
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
