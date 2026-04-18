<script setup>
import { computed, ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { store, connect as provisionConnect, toast } from '../store.js';
import { t } from '../i18n.js';
import { detectDevice, hap, openExternal } from '../telegram.js';
import { useClipboard } from '../composables/useClipboard.js';
import ConnectButton from '../components/ConnectButton.vue';
import FlagIcon from '../components/FlagIcon.vue';
import QrCode from '../components/QrCode.vue';
import PingBadge from '../components/PingBadge.vue';
import LoadBar from '../components/LoadBar.vue';

const route = useRoute();
const slug = computed(() => route.params.slug);
const server = computed(() => store.servers.find((s) => s.slug === slug.value));

const payload = ref(null);
const loading = ref(false);
const showQr = ref(false);
const error = ref(null);

const { copy, copied } = useClipboard();
const device = detectDevice();

onMounted(async () => {
    if (!server.value) return;
    if (server.value.isComingSoon) return;
    await ensureProvisioned();
});

async function ensureProvisioned() {
    try {
        loading.value = true;
        payload.value = await provisionConnect(slug.value);
    } catch (e) {
        error.value = e?.response?.data?.message || e.message || t('common.error');
    } finally {
        loading.value = false;
    }
}

const deepLink = computed(() => payload.value?.deepLinks?.[device] || payload.value?.deepLinks?.ios || '');
const subscriptionUrl = computed(() => payload.value?.subscriptionUrl || '');
const appStoreLink = computed(() => payload.value?.appStoreLinks?.[device] || payload.value?.appStoreLinks?.ios);

async function handleConnect() {
    if (!payload.value) {
        await ensureProvisioned();
    }
    return deepLink.value;
}

async function copyUrl() {
    hap.light();
    if (await copy(subscriptionUrl.value)) {
        toast(t('server.copied'), 'success');
    }
}

function toggleQr() {
    hap.select();
    showQr.value = !showQr.value;
}

function openStore() {
    hap.light();
    if (appStoreLink.value) openExternal(appStoreLink.value);
}
</script>

<template>
    <div v-if="!server" class="py-16 text-center text-sm" style="color: var(--color-text-hint)">
        {{ t('common.loading') }}
    </div>

    <div v-else class="pt-1 space-y-5">
        <!-- Hero -->
        <div class="card p-5 flex items-center gap-4 relative overflow-hidden">
            <div aria-hidden="true"
                 class="absolute inset-0 pointer-events-none"
                 style="background: radial-gradient(80% 100% at 0% 0%, color-mix(in srgb, var(--color-button) 22%, transparent), transparent 60%); opacity: 0.8" />
            <FlagIcon :emoji="server.flagEmoji" :code="server.countryCode" :size="64" />
            <div class="flex-1 min-w-0 relative">
                <div class="text-xl font-semibold truncate">{{ server.name }}</div>
                <div class="text-xs" style="color: var(--color-text-subtitle)">
                    {{ server.city }}<span v-if="server.city">, </span>{{ server.country }}
                </div>
                <div class="mt-2 flex items-center gap-3">
                    <PingBadge :ms="server.pingMs" />
                    <LoadBar :percent="server.loadPercent" class="flex-1" />
                </div>
            </div>
        </div>

        <!-- Coming-soon fallback -->
        <div v-if="server.isComingSoon" class="card p-5">
            <div class="text-sm">{{ t('server.soon') }}</div>
            <button class="btn btn--secondary w-full mt-3" disabled>{{ t('server.notifyMe') }}</button>
        </div>

        <!-- Connect flow -->
        <div v-else class="space-y-3">
            <ConnectButton
                :label="t('server.connect')"
                :enabled="!!payload && !loading"
                :on-click="handleConnect"
            />

            <div class="grid grid-cols-2 gap-2">
                <button class="btn btn--secondary" :disabled="!subscriptionUrl" @click="copyUrl">
                    {{ copied ? t('server.copied') : t('server.copyLink') }}
                </button>
                <button class="btn btn--secondary" :disabled="!subscriptionUrl" @click="toggleQr">
                    {{ t('server.showQr') }}
                </button>
            </div>

            <transition name="page">
                <div v-if="showQr && subscriptionUrl" class="card p-4 flex flex-col items-center gap-2">
                    <QrCode :value="subscriptionUrl" :size="200" />
                    <div class="text-[11px] text-center break-all px-2 font-mono"
                         style="color: var(--color-text-hint)">{{ subscriptionUrl }}</div>
                </div>
            </transition>

            <!-- How it works -->
            <details class="card px-4 py-3 open:pb-4">
                <summary class="cursor-pointer text-sm font-medium flex items-center justify-between"
                         style="list-style: none">
                    <span>{{ t('server.how') }}</span>
                    <span style="color: var(--color-text-hint)">›</span>
                </summary>
                <div class="text-xs mt-2 leading-relaxed" style="color: var(--color-text-subtitle)">
                    {{ t('server.howBody') }}
                </div>
                <button v-if="appStoreLink" class="btn btn--secondary w-full mt-3" @click="openStore">
                    {{ t('server.download') }}
                </button>
            </details>

            <div v-if="error" class="card p-4 text-sm"
                 style="border-color: color-mix(in srgb, var(--color-destructive) 40%, transparent); color: var(--color-destructive)">
                {{ error }}
            </div>
        </div>
    </div>
</template>
