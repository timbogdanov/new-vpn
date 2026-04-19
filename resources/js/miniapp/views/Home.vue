<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';

import { store, fetchProfile } from '../store.js';
import { t } from '../i18n.js';
import { hap } from '../telegram.js';
import { hoursUntilExpiry, daysUntilExpiry, isTrialing, isExpired, activeSubscription } from '../billing.js';

import ConnectHero from '../components/ConnectHero.vue';
import OnboardingTour from '../components/OnboardingTour.vue';
import PaywallSheet from '../components/billing/PaywallSheet.vue';
import Skeleton from '../components/Skeleton.vue';
import { SectionLabel, ListGroup, ListRow } from '../components/ui/index.js';

const router = useRouter();

const recommended = computed(() => store.recommendedServer);
const paywallOpen = ref(false);

const trialing = computed(() => isTrialing());
const expired = computed(() => isExpired());
const sub = computed(() => activeSubscription());
const days = computed(() => daysUntilExpiry());

const heroStatus = computed(() => {
    if (expired.value) return 'expired';
    if (trialing.value && hoursUntilExpiry() <= 48) return 'trial-ending';
    if (!recommended.value) return 'idle';
    return 'ready';
});

const subLabel = computed(() => {
    if (!sub.value || expired.value) return t('billing.noActive');
    if (trialing.value) return t('billing.trialActive', { days: days.value });
    return t('billing.proActive', { days: days.value });
});

const totalUp = computed(() => store.profile?.totalTraffic?.up || 0);
const totalDown = computed(() => store.profile?.totalTraffic?.down || 0);
const totalTraffic = computed(() => totalUp.value + totalDown.value);
const hasTraffic = computed(() => totalTraffic.value > 0);

function formatBytes(bytes) {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let i = 0; let n = bytes;
    while (n >= 1024 && i < units.length - 1) { n /= 1024; i++; }
    return n.toFixed(n >= 100 || i === 0 ? 0 : 1) + ' ' + units[i];
}

function openPaywall() { hap.select(); paywallOpen.value = true; }

function goConnect() {
    if (!recommended.value) { openServers(); return; }
    hap.medium();
    router.push(`/servers/${recommended.value.slug}`);
}
function openServers() { hap.select(); router.push('/servers'); }
function openProfile() { hap.select(); router.push('/profile'); }
function openTools() { hap.select(); router.push('/tools'); }

onMounted(() => {
    if (!store.profile) {
        fetchProfile().catch(() => {});
    }
});
</script>

<template>
    <div class="home">
        <Skeleton v-if="!store.servers.length" :height="220" />
        <ConnectHero
            v-else
            :server="recommended"
            :ping="recommended?.pingMs"
            :status="heroStatus"
            @connect="goConnect"
            @choose="openServers"
            @paywall="openPaywall"
        />

        <section>
            <SectionLabel>{{ t('billing.title') }}</SectionLabel>
            <ListGroup>
                <ListRow chevron @click="openPaywall">
                    <template #title>{{ subLabel }}</template>
                    <template v-if="sub && !expired" #subtitle>
                        {{ t(trialing ? 'billing.plans.trial_7d.name' : 'billing.plans.pro_monthly.name') }}
                    </template>
                </ListRow>
            </ListGroup>
        </section>

        <section>
            <SectionLabel>{{ t('profile.trafficTotal') }}</SectionLabel>
            <ListGroup>
                <ListRow chevron @click="openProfile">
                    <template #title>{{ hasTraffic ? formatBytes(totalTraffic) : t('server.yourTrafficEmpty') }}</template>
                    <template #subtitle>
                        {{ t('profile.upload') }} {{ formatBytes(totalUp) }} · {{ t('profile.download') }} {{ formatBytes(totalDown) }}
                    </template>
                </ListRow>
            </ListGroup>
        </section>

        <section>
            <SectionLabel>{{ t('home.quickTools') }}</SectionLabel>
            <ListGroup>
                <ListRow :title="t('home.toolsIp')" chevron @click="openTools" />
                <ListRow :title="t('home.toolsSpeed')" chevron @click="openTools" />
            </ListGroup>
        </section>

        <OnboardingTour />
        <PaywallSheet v-model:open="paywallOpen" />
    </div>
</template>

<style scoped>
.home {
    display: flex;
    flex-direction: column;
    gap: 24px;
    padding-bottom: 32px;
}
</style>
