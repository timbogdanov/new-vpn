<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { MapPinOff } from 'lucide-vue-next';

import { store } from '../store.js';
import { t } from '../i18n.js';
import { hap } from '../telegram.js';

import ServerRow from '../components/ServerRow.vue';
import Skeleton from '../components/Skeleton.vue';
import { SegmentedControl, ListGroup, EmptyState, Card } from '../components/ui/index.js';
import { useBrowserPing } from '../composables/useBrowserPing.js';

const router = useRouter();
const sort = ref('recommended');
const { pings, ping } = useBrowserPing();

const sorted = computed(() => {
    const all = [...store.servers];
    const key = sort.value;
    return all.sort((a, b) => {
        if (a.isComingSoon !== b.isComingSoon) return a.isComingSoon ? 1 : -1;
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
    for (const s of store.availableServers) {
        if (!s.hostPreview) continue;
        await ping(s.slug, '/health', { samples: 1 });
    }
});
</script>

<template>
    <div class="px-4 space-y-4">
        <p class="subtitle">{{ t('servers.subtitle') }}</p>

        <div class="sort-bar">
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

        <div v-if="!store.servers.length" class="space-y-2">
            <Skeleton v-for="i in 5" :key="i" :height="60" />
        </div>

        <Card v-else-if="!sorted.length" padding="none">
            <EmptyState :title="t('servers.empty')">
                <template #icon>
                    <MapPinOff :size="22" :stroke-width="1.75" />
                </template>
            </EmptyState>
        </Card>

        <ListGroup v-else>
            <ServerRow
                v-for="s in sorted"
                :key="s.slug"
                :server="s"
                :ping-override="pings[s.slug] ?? null"
                @click="go"
            />
        </ListGroup>
    </div>
</template>

<style scoped>
.subtitle {
    font-size: 14px;
    line-height: 20px;
    color: var(--color-text-hint);
    margin: 0 4px;
}

.sort-bar {
    position: sticky;
    top: 0;
    z-index: 5;
    padding: 4px 0;
    background: linear-gradient(to bottom, var(--color-bg) 80%, transparent);
}
</style>
