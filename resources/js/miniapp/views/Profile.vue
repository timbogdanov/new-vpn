<script setup>
import { computed, onMounted, ref } from 'vue';
import { Copy, Check } from 'lucide-vue-next';
import { useRouter } from 'vue-router';

import { store, fetchProfile, updateProfile, ackContributeBanner, toast } from '../store.js';
import { t, setLocale } from '../i18n.js';
import { hap, openExternal, showPopup } from '../telegram.js';
import { useClipboard } from '../composables/useClipboard.js';

const router = useRouter();

import TrafficDonut from '../components/TrafficDonut.vue';
import Skeleton from '../components/Skeleton.vue';
import SubscriptionStatusCard from '../components/billing/SubscriptionStatusCard.vue';
import BillingHistory from '../components/billing/BillingHistory.vue';
import PaywallSheet from '../components/billing/PaywallSheet.vue';
import Sheet from '../components/ui/Sheet.vue';

import {
    Card, ListGroup, ListRow, IconButton,
} from '../components/ui/index.js';

const loading = ref(false);
const { copy, copied } = useClipboard();

const memberSince = computed(() => {
    const raw = store.profile?.user?.memberSince || store.user?.memberSince;
    if (!raw) return '';
    return new Date(raw).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
});

const aggregatedUrl = computed(() => store.profile?.aggregatedSubscriptionUrl || '');
const aggregatedPreview = computed(() => {
    const url = aggregatedUrl.value;
    if (!url) return '';
    return url.length > 32 ? `${url.slice(0, 32)}…` : url;
});

async function load() {
    loading.value = true;
    try { await fetchProfile(); }
    finally { loading.value = false; }
}

async function setLang(code) {
    if (!code || code === store.user?.languageCode) return;
    hap.select();
    await updateProfile({ languageCode: code });
    setLocale(code);
    toast(t('profile.langSwitched'), 'success');
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
    if (!aggregatedUrl.value) return;
    hap.light();
    if (await copy(aggregatedUrl.value)) toast(t('server.copied'), 'success');
}

function openSupport() {
    const url = store.config?.supportUrl;
    if (url) openExternal(url);
}

async function toggleContribute() {
    hap.select();
    const next = !store.user?.contributeSignals;
    try {
        const data = await updateProfile({ contributeSignals: next });
        if (store.user && data?.user) {
            store.user.contributeSignals = !!data.user.contributeSignals;
        }
    } catch (_) {
        // updateProfile swallows / toasts via store; no extra work.
    }
}

// One-time disclosure sheet for the 2026-04-19 opt-in default flip. Shown
// until the user acknowledges it (either keeping or opting out of sharing).
const showContributeBanner = computed(() => {
    return !!(store.user && store.user.contributeSignals && !store.user.contributeAckedAt);
});

function onBannerDismiss() {
    ackContributeBanner();
}

function onBannerOk() {
    hap.select();
    ackContributeBanner();
}

async function onBannerOff() {
    hap.select();
    // Optimistic: flip both local fields first so the banner closes even if
    // the API call fails (e.g. migration column missing in prod).
    if (store.user) {
        store.user.contributeSignals = false;
        store.user.contributeAckedAt = store.user.contributeAckedAt || new Date().toISOString();
    }
    try {
        await updateProfile({ contributeSignals: false });
    } catch (e) {
        // eslint-disable-next-line no-console
        console.warn('onBannerOff failed', e);
    }
}

const paywallOpen = ref(false);
const historyOpen = ref(false);
function openPaywall() { hap.select(); paywallOpen.value = true; }
function openHistory() { hap.select(); historyOpen.value = true; }

function formatBytes(bytes) {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let i = 0; let n = bytes;
    while (n >= 1024 && i < units.length - 1) { n /= 1024; i++; }
    return n.toFixed(n >= 100 || i === 0 ? 0 : 1) + ' ' + units[i];
}

onMounted(load);
</script>

<template>
    <div class="profile">
        <header class="profile__identity">
            <p class="profile__name">{{ store.user?.displayName || '—' }}</p>
            <p v-if="memberSince" class="profile__since">
                {{ t('profile.memberSince') }} · {{ memberSince }}
            </p>
        </header>

        <section class="profile__section">
            <div class="profile__label">{{ t('billing.title') }}</div>
            <SubscriptionStatusCard @upgrade="openPaywall" @history="openHistory" />
        </section>

        <section class="profile__section">
            <div class="profile__label">{{ t('profile.trafficTotal') }}</div>
            <Card padding="lg">
                <Skeleton v-if="loading && !store.profile" :height="160" />
                <template v-else>
                    <TrafficDonut
                        :up="store.profile?.totalTraffic?.up || 0"
                        :down="store.profile?.totalTraffic?.down || 0"
                    />
                    <div class="profile__traffic-rows">
                        <div class="profile__traffic-row">
                            <span>{{ t('profile.upload') }}</span>
                            <span class="profile__mono">{{ formatBytes(store.profile?.totalTraffic?.up || 0) }}</span>
                        </div>
                        <div class="profile__traffic-row">
                            <span>{{ t('profile.download') }}</span>
                            <span class="profile__mono">{{ formatBytes(store.profile?.totalTraffic?.down || 0) }}</span>
                        </div>
                    </div>
                </template>
            </Card>
        </section>

        <section v-if="store.profile?.clients?.length" class="profile__section">
            <div class="profile__label">{{ t('profile.perServer') }}</div>
            <ListGroup>
                <ListRow
                    v-for="client in store.profile.clients"
                    :key="client.uuid"
                    :title="client.server.name"
                    :interactive="false"
                >
                    <template #trailing>
                        <span class="profile__mono">{{ formatBytes(client.traffic.total) }}</span>
                    </template>
                </ListRow>
            </ListGroup>
        </section>

        <section v-if="aggregatedUrl" class="profile__section">
            <div class="profile__label">{{ t('profile.aggregated') }}</div>
            <ListGroup>
                <ListRow :interactive="false">
                    <template #title>{{ t('profile.aggregated') }}</template>
                    <template #subtitle>{{ aggregatedPreview }}</template>
                    <template #trailing>
                        <div class="profile__icon-actions">
                            <IconButton :aria-label="t('common.copy')" variant="ghost" @click="copyUrl">
                                <Check v-if="copied" :size="18" :stroke-width="1.5" />
                                <Copy v-else :size="18" :stroke-width="1.5" />
                            </IconButton>
                        </div>
                    </template>
                </ListRow>
                <ListRow :title="t('profile.rotate')" chevron @click="rotateToken" />
            </ListGroup>
            <p class="profile__hint">{{ t('profile.aggregatedHint') }}</p>
        </section>

        <section class="profile__section">
            <div class="profile__label">{{ t('profile.language') }}</div>
            <ListGroup>
                <ListRow
                    v-for="opt in [
                        { value: 'en', label: 'English' },
                        { value: 'ru', label: 'Русский' },
                    ]"
                    :key="opt.value"
                    :title="opt.label"
                    @click="setLang(opt.value)"
                >
                    <template #trailing>
                        <Check
                            v-if="(store.user?.languageCode || 'en') === opt.value"
                            :size="18"
                            :stroke-width="2"
                            class="profile__lang-check"
                        />
                    </template>
                </ListRow>
            </ListGroup>
        </section>

        <section class="profile__section">
            <div class="profile__label">{{ t('profile.contributeTitle') }}</div>
            <ListGroup>
                <ListRow @click="toggleContribute">
                    <template #title>{{ t('profile.contributeRow') }}</template>
                    <template #subtitle>
                        {{ store.user?.contributeSignals
                            ? t('profile.contributeBodyOn')
                            : t('profile.contributeBodyOff') }}
                    </template>
                    <template #trailing>
                        <span class="profile__toggle" :class="{ 'profile__toggle--on': store.user?.contributeSignals }">
                            {{ store.user?.contributeSignals ? t('profile.contributeOn') : t('profile.contributeOff') }}
                        </span>
                    </template>
                </ListRow>
                <ListRow chevron @click="router.push('/profile/my-data')">
                    <template #title>{{ t('profile.myDataRow') }}</template>
                </ListRow>
            </ListGroup>
            <p class="profile__hint">{{ t('profile.contributeHint') }}</p>
        </section>

        <Sheet
            :open="showContributeBanner"
            :aria-label="t('profile.contributeBannerTitle')"
            @update:open="(v) => !v && onBannerDismiss()"
        >
            <h3 class="profile__banner-title">{{ t('profile.contributeBannerTitle') }}</h3>
            <p class="profile__banner-body">{{ t('profile.contributeBannerBody') }}</p>
            <div class="profile__banner-actions">
                <button type="button" class="profile__banner-off" @click="onBannerOff">
                    {{ t('profile.contributeBannerOff') }}
                </button>
                <button type="button" class="profile__banner-ok" @click="onBannerOk">
                    {{ t('profile.contributeBannerOk') }}
                </button>
            </div>
        </Sheet>

        <section v-if="store.config?.supportUrl" class="profile__section">
            <ListGroup>
                <ListRow :title="t('profile.support')" chevron @click="openSupport" />
            </ListGroup>
        </section>

        <PaywallSheet v-model:open="paywallOpen" />
        <Sheet v-model:open="historyOpen" :aria-label="t('billing.history')">
            <header class="profile__sheet-head">
                <p class="profile__sheet-eyebrow">{{ t('billing.title') }}</p>
                <h2 class="profile__sheet-title font-display">{{ t('billing.history') }}</h2>
            </header>
            <BillingHistory />
        </Sheet>
    </div>
</template>

<style scoped>
.profile__banner-title {
    margin: 0 0 8px;
    font-family: var(--font-serif, 'Instrument Serif', Georgia, serif);
    font-weight: 400;
    font-size: 22px;
    line-height: 28px;
    color: var(--color-text-strong);
}
.profile__banner-body {
    margin: 0 0 16px;
    font-size: 14px;
    line-height: 20px;
    color: var(--color-text-subtle);
}
.profile__banner-actions {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
}
.profile__banner-ok {
    background: var(--color-accent);
    color: var(--color-accent-text);
    padding: 10px 16px;
    border-radius: var(--radius-md);
    font-size: 14px;
    font-weight: 500;
}
.profile__banner-off {
    background: transparent;
    color: var(--color-text-subtle);
    padding: 10px 16px;
    border-radius: var(--radius-md);
    font-size: 14px;
    font-weight: 500;
}

.profile {
    display: flex;
    flex-direction: column;
    gap: 24px;
    padding-bottom: 32px;
}

.profile__identity {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 4px 0 0;
}
.profile__name {
    margin: 0;
    font-size: 20px;
    line-height: 26px;
    font-weight: 600;
    letter-spacing: -0.01em;
    color: var(--color-text-strong);
}
.profile__since {
    margin: 0;
    font-size: 12px;
    line-height: 16px;
    color: var(--color-text-subtle);
}

.profile__section {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.profile__label {
    font-size: 11px;
    line-height: 14px;
    font-weight: 600;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--color-text-hint);
    padding: 0 16px;
}

.profile__traffic-rows {
    display: flex;
    flex-direction: column;
    gap: 8px;
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid var(--color-separator);
}
.profile__traffic-row {
    display: flex;
    justify-content: space-between;
    font-size: 13px;
    line-height: 18px;
    color: var(--color-text-subtle);
}

.profile__mono {
    font-family: var(--font-mono);
    font-size: 13px;
    color: var(--color-text-strong);
    font-variant-numeric: tabular-nums;
    font-weight: 500;
}

.profile__icon-actions {
    display: inline-flex;
    gap: 4px;
}

.profile__hint {
    margin: 0;
    padding: 0 16px;
    font-size: 12px;
    line-height: 16px;
    color: var(--color-text-hint);
}

.profile__lang-check {
    color: var(--color-accent);
}

.profile__toggle {
    display: inline-flex;
    align-items: center;
    padding: 3px 10px;
    border-radius: var(--radius-pill);
    font-size: 11px;
    line-height: 14px;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    background: color-mix(in srgb, var(--color-text-subtle) 10%, transparent);
    color: var(--color-text-subtle);
}
.profile__toggle--on {
    background: var(--color-accent-tint);
    color: var(--color-accent);
}

.profile__sheet-head {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding: 0 4px 20px;
    max-width: calc(100% - 44px);
}
.profile__sheet-eyebrow {
    margin: 0;
    font-size: 11px;
    line-height: 14px;
    font-weight: 600;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: var(--color-text-hint);
}
.profile__sheet-title {
    margin: 0;
    font-size: 28px;
    line-height: 34px;
    color: var(--color-text-strong);
}
</style>
