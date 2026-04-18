<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { store } from '../store.js';
import { t } from '../i18n.js';
import { hap } from '../telegram.js';
import ServerCard from '../components/ServerCard.vue';
import Skeleton from '../components/Skeleton.vue';
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

function pickSort(key) {
    hap.select();
    sort.value = key;
}

onMounted(async () => {
    // Background probe — nice, non-blocking, cheap.
    for (const s of store.availableServers) {
        if (!s.hostPreview) continue;
        // Use /health via primary domain for a same-origin, always-available target.
        // (Per-server hosts may not serve HTTPS HEAD — fall back to app origin as a neutral proxy latency.)
        await ping(s.slug, '/health', { samples: 1 });
    }
});
</script>

<template>
    <div class="pt-1 space-y-4">
        <div class="px-1">
            <div class="text-sm" style="color: var(--color-text-subtitle)">{{ t('servers.subtitle') }}</div>
        </div>

        <div class="flex gap-2 px-1 overflow-x-auto -mx-4 px-4" style="scroll-snap-type: x mandatory">
            <button
                v-for="opt in [
                    { k: 'recommended', l: t('servers.sortRecommended') },
                    { k: 'ping',        l: t('servers.sortPing') },
                    { k: 'load',        l: t('servers.sortLoad') },
                ]"
                :key="opt.k"
                class="chip"
                :class="sort === opt.k ? 'chip--recommended' : ''"
                :style="sort === opt.k ? '' : 'background: var(--color-bg-section)'"
                @click="pickSort(opt.k)"
            >
                {{ opt.l }}
            </button>
        </div>

        <div v-if="!store.servers.length" class="space-y-2">
            <Skeleton v-for="i in 5" :key="i" :height="74" />
        </div>

        <div v-else-if="!sorted.length" class="py-12 text-center text-sm" style="color: var(--color-text-hint)">
            {{ t('servers.empty') }}
        </div>

        <div v-else class="space-y-2">
            <ServerCard
                v-for="s in sorted"
                :key="s.slug"
                :server="s"
                :ping-override="pings[s.slug] ?? null"
                @click="go"
            />
        </div>
    </div>
</template>
