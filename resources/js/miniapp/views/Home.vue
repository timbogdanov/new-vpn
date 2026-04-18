<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';

import { store, fetchProfile } from '../store.js';
import { t, i18nState } from '../i18n.js';
import { hap } from '../telegram.js';
import { hoursUntilExpiry, isTrialing, isExpired } from '../billing.js';

import BentoGrid from '../components/bento/BentoGrid.vue';
import BentoConnectTile from '../components/bento/BentoConnectTile.vue';
import BentoSubscriptionTile from '../components/bento/BentoSubscriptionTile.vue';
import BentoStatsTile from '../components/bento/BentoStatsTile.vue';
import BentoServersTile from '../components/bento/BentoServersTile.vue';
import BentoToolsTile from '../components/bento/BentoToolsTile.vue';
import OnboardingTour from '../components/OnboardingTour.vue';
import UpgradeBanner from '../components/billing/UpgradeBanner.vue';
import PaywallSheet from '../components/billing/PaywallSheet.vue';
import Skeleton from '../components/Skeleton.vue';

const router = useRouter();

const recommended = computed(() => store.recommendedServer);
const intlLocale = computed(() => (i18nState.locale === 'ru' ? 'ru-RU' : 'en-US'));

const memberSince = computed(() => {
    const raw = store.user?.memberSince;
    if (!raw) return '';
    const d = new Date(raw);
    if (Number.isNaN(d.getTime())) return '';
    return d.toLocaleDateString(intlLocale.value, { year: 'numeric', month: 'short', day: 'numeric' });
});

const greeting = computed(() => store.user?.firstName
    ? t('home.greeting', { name: store.user.firstName })
    : t('home.subtitle'));

const paywallOpen = ref(false);
const trialing = computed(() => isTrialing());
const expired = computed(() => isExpired());

const banner = computed(() => {
    if (expired.value) {
        return { title: t('billing.expiredBanner'), severity: 'danger', cta: t('billing.renew') };
    }
    if (trialing.value && hoursUntilExpiry() <= 48) {
        return {
            title: t('billing.trialEndingBanner', { hours: hoursUntilExpiry() }),
            severity: 'warn',
            cta: t('billing.upgrade'),
        };
    }
    return null;
});

function openPaywall() { hap.select(); paywallOpen.value = true; }

function goConnect() {
    if (!recommended.value) return;
    hap.medium();
    router.push(`/servers/${recommended.value.slug}`);
}
function openServers() { hap.select(); router.push('/servers'); }
function openProfile() { hap.select(); router.push('/profile'); }
function openTools() { hap.select(); router.push('/tools'); }

onMounted(() => {
    if (!store.profile) {
        // Background-load so BentoStatsTile populates without blocking paint.
        fetchProfile().catch(() => {});
    }
});
</script>

<template>
    <div class="home px-4 space-y-5">
        <header class="home__head">
            <p class="home__greet">{{ greeting }}</p>
            <p v-if="memberSince" class="home__sub">{{ t('profile.memberSince') }} · {{ memberSince }}</p>
        </header>

        <UpgradeBanner
            v-if="banner"
            :title="banner.title"
            :severity="banner.severity"
            :cta="banner.cta"
            @cta="openPaywall"
        />

        <Skeleton v-if="!store.servers.length" :height="260" />
        <BentoGrid v-else>
            <BentoConnectTile @connect="goConnect" @paywall="openPaywall" />
            <BentoSubscriptionTile @paywall="openPaywall" />
            <BentoStatsTile @click="openProfile" />
            <BentoServersTile @click="openServers" />
            <BentoToolsTile @ip="openTools" @speed="openTools" />
        </BentoGrid>

        <OnboardingTour />
        <PaywallSheet v-model:open="paywallOpen" />
    </div>
</template>

<style scoped>
.home__head { padding: 4px 4px 0; }
.home__greet {
    font-family: var(--font-display);
    font-size: var(--text-display);
    line-height: var(--text-display--line-height);
    margin: 0;
    font-weight: 600;
    letter-spacing: -0.01em;
}
.home__sub { color: var(--color-text-hint); font-size: var(--text-label); margin: 4px 0 0; }
</style>
