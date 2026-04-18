<script setup>
import { computed, onMounted, ref } from 'vue';
import { LifeBuoy, RotateCcw, Copy, Check, User } from 'lucide-vue-next';

import { store, fetchProfile, updateProfile, toast } from '../store.js';
import { t, setLocale } from '../i18n.js';
import { hap, openExternal, showPopup } from '../telegram.js';
import { useClipboard } from '../composables/useClipboard.js';

import TrafficDonut from '../components/TrafficDonut.vue';
import Skeleton from '../components/Skeleton.vue';
import FlagIcon from '../components/FlagIcon.vue';
import SubscriptionStatusCard from '../components/billing/SubscriptionStatusCard.vue';
import BillingHistory from '../components/billing/BillingHistory.vue';
import PaywallSheet from '../components/billing/PaywallSheet.vue';
import Sheet from '../components/ui/Sheet.vue';

import {
    Card, SectionLabel, ListGroup, ListRow, IconButton,
    SegmentedControl, PageHeader,
} from '../components/ui/index.js';

const loading = ref(false);
const { copy, copied } = useClipboard();

const memberSince = computed(() => {
    const raw = store.profile?.user?.memberSince;
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
    <div class="px-4 space-y-6">
        <!-- Identity -->
        <PageHeader :title="store.user?.displayName || '—'">
            <template #leading>
                <div class="identity-avatar">
                    <img
                        v-if="store.user?.photoUrl"
                        :src="store.user.photoUrl"
                        :alt="store.user.displayName"
                        class="identity-avatar__img"
                        referrerpolicy="no-referrer"
                    >
                    <User v-else :size="22" :stroke-width="1.75" />
                </div>
            </template>
            <template v-if="memberSince" #subtitle>
                {{ t('profile.memberSince') }} · {{ memberSince }}
            </template>
        </PageHeader>

        <!-- Subscription -->
        <section>
            <SectionLabel>{{ t('billing.title') }}</SectionLabel>
            <SubscriptionStatusCard @upgrade="openPaywall" @history="openHistory" />
        </section>

        <!-- Traffic usage -->
        <section>
            <SectionLabel>{{ t('profile.trafficTotal') }}</SectionLabel>
            <Card>
                <Skeleton v-if="loading && !store.profile" :height="160" />
                <TrafficDonut
                    v-else
                    :up="store.profile?.totalTraffic?.up || 0"
                    :down="store.profile?.totalTraffic?.down || 0"
                />
            </Card>
        </section>

        <!-- Per-server breakdown -->
        <section v-if="store.profile?.clients?.length">
            <SectionLabel>{{ t('profile.perServer') }}</SectionLabel>
            <ListGroup>
                <ListRow
                    v-for="client in store.profile.clients"
                    :key="client.uuid"
                    :title="client.server.name"
                    :interactive="false"
                >
                    <template #leading>
                        <FlagIcon :code="client.server.countryCode" :size="32" />
                    </template>
                    <template #trailing>
                        <span class="tabular-nums traffic-amount">{{ formatBytes(client.traffic.total) }}</span>
                    </template>
                </ListRow>
            </ListGroup>
        </section>

        <!-- Universal subscription -->
        <section v-if="aggregatedUrl">
            <SectionLabel>{{ t('profile.aggregated') }}</SectionLabel>
            <ListGroup>
                <ListRow
                    :title="t('profile.aggregated')"
                    :subtitle="aggregatedPreview"
                    :interactive="false"
                >
                    <template #trailing>
                        <div class="sub-actions">
                            <IconButton :aria-label="t('common.copy')" variant="ghost" @click="copyUrl">
                                <Check v-if="copied" :size="18" :stroke-width="1.75" />
                                <Copy v-else :size="18" :stroke-width="1.75" />
                            </IconButton>
                            <IconButton :aria-label="t('profile.rotate')" variant="ghost" @click="rotateToken">
                                <RotateCcw :size="18" :stroke-width="1.75" />
                            </IconButton>
                        </div>
                    </template>
                </ListRow>
            </ListGroup>
            <p class="sub-hint">{{ t('profile.aggregatedHint') }}</p>
        </section>

        <!-- Language -->
        <section>
            <SectionLabel>{{ t('profile.language') }}</SectionLabel>
            <SegmentedControl
                :model-value="store.user?.languageCode || 'en'"
                :options="[
                    { value: 'en', label: 'English' },
                    { value: 'ru', label: 'Русский' },
                ]"
                :aria-label="t('profile.language')"
                @update:model-value="setLang"
            />
        </section>

        <!-- Support -->
        <section v-if="store.config?.supportUrl">
            <ListGroup>
                <ListRow :title="t('profile.support')" chevron @click="openSupport">
                    <template #leading>
                        <LifeBuoy :size="20" :stroke-width="1.75" />
                    </template>
                </ListRow>
            </ListGroup>
        </section>

        <PaywallSheet v-model:open="paywallOpen" />
        <Sheet v-model:open="historyOpen" :aria-label="t('billing.history')">
            <h2 class="bill-history-head">{{ t('billing.history') }}</h2>
            <BillingHistory />
        </Sheet>
    </div>
</template>

<style scoped>
.identity-avatar {
    width: 44px;
    height: 44px;
    border-radius: var(--radius-pill);
    overflow: hidden;
    background: var(--color-surface-raised);
    color: var(--color-text-subtle);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 0 0 1px var(--color-separator) inset;
}
.identity-avatar__img { width: 100%; height: 100%; object-fit: cover; }

.traffic-amount {
    font-size: 13px;
    color: var(--color-text);
    font-weight: 500;
}

.sub-actions { display: inline-flex; gap: 4px; }

.sub-hint {
    margin: 10px 4px 0;
    font-size: 12px;
    line-height: 16px;
    color: var(--color-text-hint);
}

.bill-history-head {
    font-family: var(--font-display);
    font-size: var(--text-display);
    line-height: var(--text-display--line-height);
    margin: 0 0 16px;
    font-weight: 600;
}
</style>
