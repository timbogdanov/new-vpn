<script setup>
import { computed } from 'vue';
import { RouterView, useRoute, useRouter } from 'vue-router';

import { store } from '../store.js';
import { t } from '../i18n.js';
import { hap } from '../telegram.js';

import Toast from './Toast.vue';
import Spinner from './Spinner.vue';

const route = useRoute();
const router = useRouter();

const title = computed(() => {
    switch (route.name) {
        case 'servers': return t('servers.title');
        case 'profile': return t('profile.title');
        case 'tools':   return t('tools.title');
        case 'server-detail': return store.servers.find((s) => s.slug === route.params.slug)?.name || '';
        case 'dev-ui':  return 'UI';
        default: return '';
    }
});

const greeting = computed(() => t('home.greeting', { name: store.user?.firstName || '' }).replace(/,\s*$/, ''));

function goProfile() {
    hap.select();
    router.push('/profile');
}
function dismissError() {
    store.error = null;
}
</script>

<template>
    <div class="shell">
        <!-- Home header: greeting + text-only "Account" shortcut.
             No decorative icons. Serif display for EN; sans for ru (handled by .font-display). -->
        <header v-if="route.name === 'home'" class="shell-header shell-header--home pt-safe">
            <div class="shell-header__body">
                <p class="shell-header__eyebrow">{{ t('home.subtitle') }}</p>
                <h1 class="shell-header__title font-display">{{ greeting }}</h1>
            </div>
            <button type="button" class="shell-header__link" @click="goProfile">
                {{ t('home.account') }}
            </button>
        </header>

        <!-- Sub-page header: left-aligned page title.
             Telegram's BackButton handles navigation; no decorative icons. -->
        <header v-else class="shell-header shell-header--sub pt-safe">
            <h1 class="shell-header__subtitle">{{ title }}</h1>
        </header>

        <main class="shell-main pb-safe">
            <div v-if="store.error" class="shell-error">
                <span class="shell-error__dot" aria-hidden="true"></span>
                <span class="shell-error__msg">{{ store.error }}</span>
                <button class="shell-error__close" @click="dismissError">{{ t('common.close') }}</button>
            </div>

            <router-view v-slot="{ Component }">
                <transition name="page" mode="out-in">
                    <component :is="Component" />
                </transition>
            </router-view>

            <Spinner v-if="store.loading && !store.ready" overlay />
        </main>

        <Toast />
    </div>
</template>

<style scoped>
.shell {
    min-height: var(--tg-viewport-stable-height, 100vh);
    display: flex;
    flex-direction: column;
}

.shell-header {
    position: sticky;
    top: 0;
    z-index: 20;
    padding-left: 16px;
    padding-right: 16px;
    padding-bottom: 12px;
    background: var(--color-bg);
    display: flex;
    align-items: flex-end;
    gap: 12px;
}

.shell-header--home {
    padding-top: calc(var(--safe-top) + 16px);
    padding-bottom: 20px;
}

.shell-header--sub {
    padding-top: calc(var(--safe-top) + 12px);
    padding-bottom: 16px;
}

.shell-header__body {
    flex: 1 1 auto;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.shell-header__eyebrow {
    margin: 0;
    font-size: 11px;
    line-height: 14px;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--color-text-hint);
    font-weight: 600;
}
.shell-header__title {
    margin: 4px 0 0;
    font-size: 28px;
    line-height: 34px;
    color: var(--color-text-strong);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.shell-header__subtitle {
    margin: 0;
    font-size: 20px;
    line-height: 26px;
    font-weight: 600;
    letter-spacing: -0.01em;
    color: var(--color-text-strong);
    flex: 1 1 auto;
}
.shell-header__link {
    flex-shrink: 0;
    align-self: flex-end;
    padding: 6px 2px;
    font-size: 13px;
    line-height: 18px;
    font-weight: 500;
    color: var(--color-text-subtle);
    transition: color var(--duration-fast) var(--ease-standard);
}
.shell-header__link:active {
    color: var(--color-text-strong);
}

.shell-main {
    flex: 1 1 auto;
    position: relative;
    padding: 0 16px;
}

.shell-error {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 0 0 12px;
    padding: 12px 14px;
    border-radius: var(--radius-md);
    background: var(--color-surface-raised);
    border-left: 3px solid var(--color-danger);
    font-size: 13px;
    line-height: 18px;
    color: var(--color-text-strong);
}
.shell-error__dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--color-danger);
    flex-shrink: 0;
}
.shell-error__msg { flex: 1 1 auto; }
.shell-error__close {
    font-size: 13px;
    font-weight: 500;
    color: var(--color-accent);
}
</style>
