<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';

import { store } from '../store.js';
import { t } from '../i18n.js';
import { hap } from '../telegram.js';

import ServerRow from '../components/ServerRow.vue';
import Skeleton from '../components/Skeleton.vue';
import { SegmentedControl, ListGroup, EmptyState, Button } from '../components/ui/index.js';
import { useBrowserPing } from '../composables/useBrowserPing.js';

const router = useRouter();
const sort = ref('recommended');
const { pings, ping } = useBrowserPing();

const available = computed(() => store.servers.filter((s) => !s.isComingSoon));
const comingSoon = computed(() => store.servers.filter((s) => s.isComingSoon));

const sortedAvailable = computed(() => {
    const all = [...available.value];
    const key = sort.value;
    return all.sort((a, b) => {
        if (key === 'ping') return (pings.value[a.slug] ?? a.pingMs ?? 9999) - (pings.value[b.slug] ?? b.pingMs ?? 9999);
        if (key === 'load') return (a.loadPercent ?? 100) - (b.loadPercent ?? 100);
        return 0;
    });
});

function go(server) {
    if (server.isComingSoon) { hap.warning(); return; }
    hap.select();
    router.push(`/servers/${server.slug}`);
}

function onSort(v) {
    hap.select();
    sort.value = v;
}

onMounted(async () => {
    for (const s of store.availableServers || []) {
        if (!s.hostPreview) continue;
        await ping(s.slug, '/health', { samples: 1 });
    }
});
</script>

<template>
    <div class="list">
        <p class="list__subtitle">{{ t('servers.subtitle') }}</p>

        <div class="list__sort">
            <SegmentedControl
                :model-value="sort"
                :options="[
                    { value: 'recommended', label: t('servers.sortRecommended') },
                    { value: 'ping',        label: t('servers.sortPing') },
                    { value: 'load',        label: t('servers.sortLoad') },
                ]"
                :aria-label="t('servers.title')"
                @update:model-value="onSort"
            />
        </div>

        <div v-if="!store.servers.length" class="list__skel">
            <Skeleton v-for="i in 5" :key="i" :height="64" />
        </div>

        <EmptyState
            v-else-if="!sortedAvailable.length && !comingSoon.length"
            :title="t('servers.empty')"
            :body="t('servers.subtitle')"
        >
            <template #action>
                <Button variant="ghost" size="sm" @click="onSort('recommended')">{{ t('common.retry') }}</Button>
            </template>
        </EmptyState>

        <template v-else>
            <ListGroup v-if="sortedAvailable.length">
                <ServerRow
                    v-for="s in sortedAvailable"
                    :key="s.slug"
                    :server="s"
                    :ping-override="pings[s.slug] ?? null"
                    @click="go"
                />
            </ListGroup>

            <section v-if="comingSoon.length" class="list__section">
                <div class="list__section-label">{{ t('servers.comingSoon') }}</div>
                <ListGroup>
                    <ServerRow
                        v-for="s in comingSoon"
                        :key="s.slug"
                        :server="s"
                        @click="go"
                    />
                </ListGroup>
            </section>
        </template>
    </div>
</template>

<style scoped>
.list {
    display: flex;
    flex-direction: column;
    gap: 16px;
    padding-bottom: 32px;
}

.list__subtitle {
    font-size: 13px;
    line-height: 18px;
    color: var(--color-text-subtle);
    margin: 0;
}

.list__sort {
    padding: 4px 0;
}

.list__skel {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.list__section {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 8px;
}
.list__section-label {
    font-size: 11px;
    line-height: 14px;
    font-weight: 600;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--color-text-hint);
    padding: 0 16px;
}
</style>
