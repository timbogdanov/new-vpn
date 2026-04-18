<script setup>
import { onMounted, ref } from 'vue';
import { store, fetchProfile, updateProfile, toast } from '../store.js';
import { t, setLocale } from '../i18n.js';
import { hap, openExternal, showPopup } from '../telegram.js';
import { useClipboard } from '../composables/useClipboard.js';
import TrafficDonut from '../components/TrafficDonut.vue';
import Skeleton from '../components/Skeleton.vue';
import FlagIcon from '../components/FlagIcon.vue';

const loading = ref(false);
const { copy, copied } = useClipboard();

async function load() {
    loading.value = true;
    try { await fetchProfile(); }
    finally { loading.value = false; }
}

async function setLang(code) {
    if (code === store.user?.languageCode) return;
    hap.select();
    await updateProfile({ languageCode: code });
    setLocale(code);
    toast(code === 'ru' ? 'Язык переключён' : 'Language switched', 'success');
}

async function rotateToken() {
    hap.warning();
    const choice = await showPopup({
        title: t('profile.rotate'),
        message: t('profile.rotateConfirm'),
        buttons: [
            { id: 'ok', type: 'destructive', text: t('profile.rotate') },
            { id: 'cancel', type: 'cancel' },
        ],
    });
    if (choice === 'ok') {
        await updateProfile({ rotateSubToken: true });
        toast(t('profile.rotate'), 'success');
    }
}

async function copyUrl() {
    const url = store.profile?.aggregatedSubscriptionUrl;
    if (!url) return;
    hap.light();
    if (await copy(url)) toast(t('server.copied'), 'success');
}

function openSupport() {
    const url = store.config?.supportUrl;
    if (url) openExternal(url);
}

onMounted(load);
</script>

<template>
    <div class="pt-1 space-y-4">
        <!-- Identity card -->
        <div class="card p-5 flex items-center gap-4">
            <div class="w-14 h-14 rounded-full overflow-hidden flex items-center justify-center"
                 style="background: var(--color-bg-secondary)">
                <img
                    v-if="store.user?.photoUrl"
                    :src="store.user.photoUrl"
                    :alt="store.user.displayName"
                    class="w-full h-full object-cover"
                    referrerpolicy="no-referrer"
                >
                <span v-else style="font-size:22px">👤</span>
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-lg font-semibold truncate">{{ store.user?.displayName || '—' }}</div>
                <div v-if="store.profile?.user?.memberSince" class="text-xs" style="color: var(--color-text-hint)">
                    {{ t('profile.memberSince') }} {{ new Date(store.profile.user.memberSince).toLocaleDateString() }}
                </div>
            </div>
        </div>

        <!-- Aggregated traffic -->
        <div class="card p-5">
            <div class="text-xs uppercase tracking-wider mb-3" style="color: var(--color-text-hint)">
                {{ t('profile.trafficTotal') }}
            </div>
            <Skeleton v-if="loading && !store.profile" :height="104" />
            <TrafficDonut
                v-else
                :up="store.profile?.totalTraffic?.up || 0"
                :down="store.profile?.totalTraffic?.down || 0"
            />
        </div>

        <!-- Per-server breakdown -->
        <section v-if="store.profile?.clients?.length">
            <div class="px-1 mb-2 text-sm font-semibold">{{ t('profile.perServer') }}</div>
            <div class="space-y-2">
                <div v-for="client in store.profile.clients" :key="client.uuid"
                     class="card p-4 flex items-center gap-3">
                    <FlagIcon :emoji="client.server.flagEmoji" :code="client.server.countryCode" :size="36" />
                    <div class="flex-1 min-w-0">
                        <div class="text-sm font-medium truncate">{{ client.server.name }}</div>
                        <div class="text-[11px]" style="color: var(--color-text-hint)">
                            {{ formatBytes(client.traffic.total) }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Aggregated subscription -->
        <section v-if="store.profile?.aggregatedSubscriptionUrl">
            <div class="px-1 mb-2 text-sm font-semibold">{{ t('profile.aggregated') }}</div>
            <div class="card p-4 space-y-3">
                <div class="text-xs" style="color: var(--color-text-subtitle)">{{ t('profile.aggregatedHint') }}</div>
                <div
                    class="text-[11px] font-mono break-all rounded-xl px-3 py-2"
                    style="background: rgba(255,255,255,0.04); color: var(--color-text-subtitle)"
                >{{ store.profile.aggregatedSubscriptionUrl }}</div>
                <div class="grid grid-cols-2 gap-2">
                    <button class="btn btn--secondary" @click="copyUrl">
                        {{ copied ? t('server.copied') : t('common.copy') }}
                    </button>
                    <button class="btn btn--secondary" @click="rotateToken" style="color: var(--color-destructive)">
                        {{ t('profile.rotate') }}
                    </button>
                </div>
            </div>
        </section>

        <!-- Language -->
        <section>
            <div class="px-1 mb-2 text-sm font-semibold">{{ t('profile.language') }}</div>
            <div class="card p-2 flex gap-1">
                <button
                    v-for="lang in [{ k: 'ru', l: 'Русский' }, { k: 'en', l: 'English' }]"
                    :key="lang.k"
                    class="flex-1 py-3 rounded-xl text-sm font-medium"
                    :style="store.user?.languageCode === lang.k ? 'background: var(--color-button); color: var(--color-button-text)' : 'color: var(--color-text)'"
                    @click="setLang(lang.k)"
                >
                    {{ lang.l }}
                </button>
            </div>
        </section>

        <!-- Support -->
        <section v-if="store.config?.supportUrl">
            <button class="card w-full p-4 flex items-center justify-between text-left" @click="openSupport">
                <span class="text-sm font-medium">{{ t('profile.support') }}</span>
                <span style="color: var(--color-text-hint)">›</span>
            </button>
        </section>
    </div>
</template>

<script>
function formatBytes(bytes) {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let i = 0; let n = bytes;
    while (n >= 1024 && i < units.length - 1) { n /= 1024; i++; }
    return n.toFixed(n >= 100 || i === 0 ? 0 : 1) + ' ' + units[i];
}
</script>
