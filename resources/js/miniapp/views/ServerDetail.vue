<script setup>
import { computed, ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { Copy, Check, QrCode as QrCodeIcon, HelpCircle, Clock, Download } from 'lucide-vue-next';

import { store, connect as provisionConnect, fetchProfile, toast } from '../store.js';
import { t } from '../i18n.js';
import { detectDevice, hap, openExternal, showPopup } from '../telegram.js';
import { useClipboard } from '../composables/useClipboard.js';

import ConnectButton from '../components/ConnectButton.vue';
import FlagIcon from '../components/FlagIcon.vue';
import QrCode from '../components/QrCode.vue';
import ServerFacts from '../components/ServerFacts.vue';

import {
    PageHeader, Card, ListGroup, ListRow, IconButton, Button,
    Sheet, EmptyState, Chip,
} from '../components/ui/index.js';

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
    // Kick off provisioning immediately; load profile in the background so the
    // "Your traffic" tile can populate without blocking the connect flow.
    ensureProvisioned();
    if (!store.profile) {
        fetchProfile().catch(() => {});
    }
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

const rawDeepLink = computed(() => payload.value?.deepLinks?.[device] || payload.value?.deepLinks?.ios || '');

// Telegram's WebApp.openLink() only handles https URLs — it silently drops
// custom schemes like v2raytun://. The backend exposes a redirect wrapper
// (https://…/vpn-link?url=) that 302s into the custom scheme; wrapping the
// deep link through it lets the VPN app actually open from Telegram.
const openUrl = computed(() => {
    const deep = rawDeepLink.value;
    const base = payload.value?.redirectUrlBase;
    if (!deep) return '';
    if (!base || /^https?:/i.test(deep)) return deep;
    return base + encodeURIComponent(deep);
});

const subscriptionUrl = computed(() => payload.value?.subscriptionUrl || '');
const subscriptionPreview = computed(() => {
    const url = subscriptionUrl.value;
    if (!url) return '';
    return url.length > 28 ? `${url.slice(0, 28)}…` : url;
});
const appStoreLink = computed(() => payload.value?.appStoreLinks?.[device] || payload.value?.appStoreLinks?.ios);

const ping = computed(() => server.value?.pingMs ?? null);
const loadPct = computed(() => server.value?.loadPercent ?? null);
const loadTone = computed(() => {
    const p = loadPct.value;
    if (p === null) return 'neutral';
    if (p >= 75) return 'warning';
    return 'success';
});
const loadLabel = computed(() => {
    const p = loadPct.value;
    if (p === null) return null;
    if (p >= 75) return t('server.loadBusy');
    if (p >= 40) return t('server.loadModerate');
    return t('server.loadLow');
});

async function provisionIfNeeded() {
    if (!payload.value) await ensureProvisioned();
    return openUrl.value;
}

async function copyUrl() {
    hap.light();
    if (await copy(subscriptionUrl.value)) toast(t('server.copied'), 'success');
}

function toggleQr() {
    hap.select();
    showQr.value = !showQr.value;
}

function openStore() {
    hap.light();
    if (appStoreLink.value) openExternal(appStoreLink.value);
}

async function openHelp() {
    hap.light();
    await showPopup({
        title: t('server.how'),
        message: t('server.howBody'),
        buttons: [{ id: 'ok', type: 'default', text: t('common.close') }],
    });
}
</script>

<template>
    <div v-if="!server" class="px-4 py-16 text-center" style="color: var(--color-text-hint)">
        {{ t('common.loading') }}
    </div>

    <div v-else class="px-4 space-y-6">
        <!-- Hero -->
        <PageHeader :title="server.name">
            <template #leading>
                <FlagIcon :code="server.countryCode" :size="52" />
            </template>
            <template #subtitle>
                <span>{{ [server.city, server.country].filter(Boolean).join(', ') }}</span>
                <span v-if="ping != null" class="tabular-nums hero-sep">· {{ Math.round(ping) }} ms</span>
                <Chip v-if="loadLabel" :tone="loadTone" dot class="hero-chip">{{ loadLabel }}</Chip>
            </template>
        </PageHeader>

        <!-- Coming soon -->
        <Card v-if="server.isComingSoon" padding="none">
            <EmptyState :title="t('server.soon')" :body="t('server.soonBody')">
                <template #icon>
                    <Clock :size="22" :stroke-width="1.75" />
                </template>
                <template #action>
                    <Button variant="ghost" size="sm" disabled>{{ t('server.notifyMe') }}</Button>
                </template>
            </EmptyState>
        </Card>

        <!-- Connect flow -->
        <div v-else class="space-y-5">
            <ConnectButton
                :label="t('server.connect')"
                :url="openUrl"
                :provision="provisionIfNeeded"
            />

            <!-- At-a-glance facts -->
            <ServerFacts :server="server" />

            <!-- Subscription + actions -->
            <section v-if="subscriptionUrl">
                <ListGroup>
                    <ListRow
                        :title="t('profile.aggregated')"
                        :subtitle="subscriptionPreview"
                        :interactive="false"
                    >
                        <template #trailing>
                            <div class="sub-actions">
                                <IconButton :aria-label="t('common.copy')" variant="ghost" @click="copyUrl">
                                    <Check v-if="copied" :size="18" :stroke-width="1.75" />
                                    <Copy v-else :size="18" :stroke-width="1.75" />
                                </IconButton>
                                <IconButton :aria-label="t('server.showQr')" variant="ghost" @click="toggleQr">
                                    <QrCodeIcon :size="18" :stroke-width="1.75" />
                                </IconButton>
                            </div>
                        </template>
                    </ListRow>
                </ListGroup>
            </section>

            <!-- Help + download -->
            <section>
                <ListGroup>
                    <ListRow :title="t('server.how')" chevron @click="openHelp">
                        <template #leading>
                            <HelpCircle :size="20" :stroke-width="1.75" />
                        </template>
                    </ListRow>
                    <ListRow v-if="appStoreLink" :title="t('server.download')" chevron @click="openStore">
                        <template #leading>
                            <Download :size="20" :stroke-width="1.75" />
                        </template>
                    </ListRow>
                </ListGroup>
            </section>

            <div v-if="error" class="error-card">
                {{ error }}
            </div>
        </div>

        <!-- QR in bottom sheet -->
        <Sheet v-model:open="showQr" :aria-label="t('server.qrAria')">
            <div class="qr-sheet">
                <QrCode :value="subscriptionUrl" :size="220" />
                <p class="qr-sheet__caption">{{ t('profile.aggregated') }}</p>
                <p class="qr-sheet__url tabular-nums">{{ subscriptionUrl }}</p>
            </div>
        </Sheet>
    </div>
</template>

<style scoped>
.hero-sep { color: var(--color-text-subtle); }
.hero-chip { margin-left: 2px; }

.sub-actions { display: inline-flex; gap: 4px; }

.error-card {
    padding: 14px 16px;
    border-radius: var(--radius-md);
    border: 1px solid color-mix(in srgb, var(--color-destructive) 40%, transparent);
    color: var(--color-destructive);
    font-size: 13px;
    line-height: 18px;
}

.qr-sheet {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 8px 4px 4px;
}
.qr-sheet__caption {
    margin: 16px 0 4px;
    font-size: 13px;
    font-weight: 600;
    color: var(--color-text);
}
.qr-sheet__url {
    margin: 0;
    max-width: 100%;
    font-family: var(--font-mono);
    font-size: 11px;
    line-height: 16px;
    color: var(--color-text-hint);
    text-align: center;
    word-break: break-all;
    padding: 0 4px;
}
</style>
