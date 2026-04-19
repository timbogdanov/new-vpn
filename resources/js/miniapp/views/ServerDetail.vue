<script setup>
import { computed, ref, onMounted } from 'vue';
import { useRoute } from 'vue-router';
import { Copy, Check } from 'lucide-vue-next';

import { store, connect as provisionConnect, fetchProfile, toast } from '../store.js';
import { t } from '../i18n.js';
import { detectDevice, hap, openExternal, showPopup } from '../telegram.js';
import { useClipboard } from '../composables/useClipboard.js';

import ConnectButton from '../components/ConnectButton.vue';
import ServerStatusHero from '../components/ServerStatusHero.vue';
import QrCode from '../components/QrCode.vue';

import {
    ListGroup, ListRow, IconButton, Button, Sheet, EmptyState,
} from '../components/ui/index.js';
import { isTrialing, isExpired, hoursUntilExpiry } from '../billing.js';

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

const heroStatus = computed(() => {
    if (isExpired()) return 'expired';
    if (isTrialing() && hoursUntilExpiry() <= 48) return 'trial-ending';
    if (loading.value && !payload.value) return 'provisioning';
    if (payload.value) return 'connected';
    return 'idle';
});

const trafficUsed = computed(() => {
    const slug = server.value?.slug;
    if (!slug) return null;
    return store.profile?.clients?.find((c) => c.server?.slug === slug)?.traffic?.total ?? null;
});

function formatBytes(bytes) {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let i = 0; let n = bytes;
    while (n >= 1024 && i < units.length - 1) { n /= 1024; i++; }
    return n.toFixed(n >= 100 || i === 0 ? 0 : 1) + ' ' + units[i];
}

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
    <div v-if="!server" class="detail-loading">
        {{ t('common.loading') }}
    </div>

    <div v-else class="detail">
        <ServerStatusHero
            :server="server"
            :ping="server.pingMs"
            :status="heroStatus"
        />

        <EmptyState
            v-if="server.isComingSoon"
            :title="t('server.soon')"
            :body="t('server.soonBody')"
        >
            <template #action>
                <Button variant="ghost" size="sm" disabled>{{ t('server.notifyMe') }}</Button>
            </template>
        </EmptyState>

        <template v-else>
            <ConnectButton
                :label="t('server.connect')"
                :url="openUrl"
                :provision="provisionIfNeeded"
            />

            <section class="detail__section">
                <div class="detail__label">{{ t('server.details') }}</div>
                <ListGroup>
                    <ListRow :interactive="false">
                        <template #title>{{ t('server.protocol') }}</template>
                        <template #trailing>{{ t('server.protocolValue') }}</template>
                    </ListRow>
                    <ListRow :interactive="false">
                        <template #title>{{ t('server.endpoint') }}</template>
                        <template #trailing>
                            <span class="detail__mono">{{ server.hostPreview || t('server.endpointUnknown') }}</span>
                        </template>
                    </ListRow>
                    <ListRow :interactive="false">
                        <template #title>{{ t('server.yourTraffic') }}</template>
                        <template #trailing>
                            <span class="detail__mono">{{ trafficUsed != null ? formatBytes(trafficUsed) : t('server.yourTrafficEmpty') }}</span>
                        </template>
                    </ListRow>
                </ListGroup>
            </section>

            <section v-if="subscriptionUrl" class="detail__section">
                <div class="detail__label">{{ t('profile.aggregated') }}</div>
                <ListGroup>
                    <ListRow :interactive="false">
                        <template #title>{{ t('server.copyLink') }}</template>
                        <template #subtitle>{{ subscriptionPreview }}</template>
                        <template #trailing>
                            <div class="detail__actions">
                                <IconButton :aria-label="t('common.copy')" variant="ghost" @click="copyUrl">
                                    <Check v-if="copied" :size="18" :stroke-width="1.5" />
                                    <Copy v-else :size="18" :stroke-width="1.5" />
                                </IconButton>
                            </div>
                        </template>
                    </ListRow>
                    <ListRow :title="t('server.showQr')" chevron @click="toggleQr" />
                    <ListRow v-if="appStoreLink" :title="t('server.download')" chevron @click="openStore" />
                </ListGroup>
            </section>

            <section class="detail__section">
                <div class="detail__label">{{ t('server.how') }}</div>
                <ListGroup>
                    <ListRow :title="t('server.how')" chevron @click="openHelp" />
                </ListGroup>
            </section>

            <div v-if="error" class="detail__error">{{ error }}</div>
        </template>

        <Sheet v-model:open="showQr" :aria-label="t('server.qrAria')">
            <div class="qr-sheet">
                <QrCode :value="subscriptionUrl" :size="220" />
                <p class="qr-sheet__caption">{{ t('profile.aggregated') }}</p>
                <p class="qr-sheet__url">{{ subscriptionUrl }}</p>
            </div>
        </Sheet>
    </div>
</template>

<style scoped>
.detail {
    display: flex;
    flex-direction: column;
    gap: 20px;
    padding-bottom: 32px;
}

.detail-loading {
    padding: 64px 0;
    text-align: center;
    color: var(--color-text-hint);
    font-size: 14px;
}

.detail__section {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.detail__label {
    font-size: 11px;
    line-height: 14px;
    font-weight: 600;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--color-text-hint);
    padding: 0 16px;
}

.detail__mono {
    font-family: var(--font-mono);
    font-size: 13px;
    color: var(--color-text-subtle);
}

.detail__actions {
    display: inline-flex;
    gap: 4px;
}

.detail__error {
    padding: 12px 14px;
    border-radius: var(--radius-md);
    background: var(--color-surface-raised);
    border-left: 3px solid var(--color-danger);
    color: var(--color-danger);
    font-size: 13px;
    line-height: 18px;
}

.qr-sheet {
    display: flex;
    flex-direction: column;
    align-items: center;
    padding: 8px 4px 4px;
    gap: 12px;
}
.qr-sheet__caption {
    margin: 0;
    font-size: 13px;
    font-weight: 500;
    color: var(--color-text-strong);
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
