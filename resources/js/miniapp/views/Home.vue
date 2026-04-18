<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { store } from '../store.js';
import { t } from '../i18n.js';
import { hap } from '../telegram.js';
import ServerCard from '../components/ServerCard.vue';
import Skeleton from '../components/Skeleton.vue';

const router = useRouter();

const featured = computed(() => {
    const pool = store.availableServers;
    if (!pool.length) return [];
    const seed = store.recommendedServer;
    const rest = pool.filter((s) => s !== seed);
    return [seed, ...rest.slice(0, 3)].filter(Boolean);
});

function go(server) {
    if (server.isComingSoon) {
        hap.warning();
        return;
    }
    hap.select();
    router.push(`/servers/${server.slug}`);
}

function openServers() {
    hap.select();
    router.push('/servers');
}

function openTools() {
    hap.select();
    router.push('/tools');
}
</script>

<template>
    <div class="pt-2 space-y-5">
        <!-- Aurora hero -->
        <div class="relative overflow-hidden card p-5">
            <div aria-hidden="true"
                 class="absolute inset-0 pointer-events-none"
                 style="background:
                    radial-gradient(120% 100% at 0% 0%, color-mix(in srgb, var(--color-button) 28%, transparent) 0%, transparent 60%),
                    radial-gradient(120% 100% at 100% 100%, color-mix(in srgb, var(--color-success) 16%, transparent) 0%, transparent 60%);
                    opacity: 0.65;"
            />
            <div class="relative">
                <div class="text-xs uppercase tracking-widest" style="color: var(--color-text-hint)">
                    {{ t('home.recommended') }}
                </div>
                <div v-if="store.recommendedServer" class="mt-2 flex items-center gap-3">
                    <div class="text-3xl">{{ store.recommendedServer.flagEmoji }}</div>
                    <div class="flex-1 min-w-0">
                        <div class="text-[22px] font-semibold truncate">{{ store.recommendedServer.name }}</div>
                        <div class="text-xs" style="color: var(--color-text-subtitle)">
                            {{ store.recommendedServer.city }}<span v-if="store.recommendedServer.city">, </span>{{ store.recommendedServer.country }}
                        </div>
                    </div>
                </div>
                <div v-else class="mt-2 text-[22px] font-semibold opacity-80">{{ t('common.loading') }}</div>

                <button
                    class="btn btn--primary w-full mt-5"
                    :disabled="!store.recommendedServer"
                    @click="go(store.recommendedServer)"
                >
                    {{ t('server.connect') }}
                </button>
            </div>
        </div>

        <!-- Server rail -->
        <section>
            <div class="flex items-center justify-between px-1 mb-2">
                <h2 class="text-sm font-semibold" style="color: var(--color-text)">
                    {{ t('home.allServers') }}
                </h2>
                <button class="text-sm" style="color: var(--color-text-accent)" @click="openServers">
                    {{ t('home.seeAll') }}
                </button>
            </div>
            <div v-if="!store.servers.length" class="space-y-2">
                <Skeleton v-for="i in 3" :key="i" :height="74" />
            </div>
            <div v-else class="space-y-2">
                <ServerCard v-for="s in featured" :key="s.slug" :server="s" @click="go" />
            </div>
        </section>

        <!-- Quick tools -->
        <section>
            <div class="px-1 mb-2 text-sm font-semibold">{{ t('home.quickTools') }}</div>
            <div class="grid grid-cols-2 gap-2">
                <button class="card p-4 text-left active:scale-[0.98] transition" @click="openTools">
                    <div class="text-2xl mb-2">🛡️</div>
                    <div class="text-sm font-medium">{{ t('home.toolsIp') }}</div>
                </button>
                <button class="card p-4 text-left active:scale-[0.98] transition" @click="openTools">
                    <div class="text-2xl mb-2">⚡</div>
                    <div class="text-sm font-medium">{{ t('home.toolsSpeed') }}</div>
                </button>
            </div>
        </section>
    </div>
</template>
