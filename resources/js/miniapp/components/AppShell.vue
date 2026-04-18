<script setup>
import { computed } from 'vue';
import { RouterView, useRoute, useRouter } from 'vue-router';
import { Home as HomeIcon, Settings2 } from 'lucide-vue-next';

import { store } from '../store.js';
import { t } from '../i18n.js';
import { hap } from '../telegram.js';

import Toast from './Toast.vue';
import Spinner from './Spinner.vue';
import { IconButton } from './ui/index.js';

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

function goHome() {
    hap.select();
    router.push('/');
}
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
        <!-- Home header: greeting + settings -->
        <header v-if="route.name === 'home'" class="shell-header shell-header--home pt-safe">
            <div class="shell-header__body">
                <p class="shell-header__eyebrow">{{ t('home.subtitle') }}</p>
                <h1 class="shell-header__title">{{ greeting }}</h1>
            </div>
            <IconButton :aria-label="t('profile.title')" variant="filled" @click="goProfile">
                <Settings2 :size="18" :stroke-width="1.75" />
            </IconButton>
        </header>

        <!-- Sub-page header: minimal title + home shortcut.
             Telegram's BackButton handles navigation; this is just visual context. -->
        <header v-else class="shell-header shell-header--sub pt-safe">
            <IconButton :aria-label="t('common.back')" variant="ghost" @click="goHome">
                <HomeIcon :size="18" :stroke-width="1.75" />
            </IconButton>
            <div class="shell-header__title shell-header__title--center">{{ title }}</div>
            <span class="shell-header__spacer" aria-hidden="true"></span>
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
    background: linear-gradient(to bottom, var(--color-bg) 70%, transparent);
    display: flex;
    align-items: center;
    gap: 12px;
}

.shell-header--home {
    padding-top: calc(var(--safe-top) + 12px);
    padding-bottom: 16px;
}

.shell-header--sub {
    padding-top: calc(var(--safe-top) + 8px);
    justify-content: space-between;
}

.shell-header__body {
    flex: 1 1 auto;
    min-width: 0;
}
.shell-header__eyebrow {
    margin: 0;
    font-size: 11px;
    line-height: 14px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--color-text-hint);
    font-weight: 600;
}
.shell-header__title {
    margin: 2px 0 0;
    font-size: 18px;
    line-height: 24px;
    font-weight: 700;
    letter-spacing: -0.01em;
    color: var(--color-text);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.shell-header__title--center {
    text-align: center;
    flex: 1 1 auto;
    font-size: 15px;
    line-height: 20px;
}
.shell-header__spacer { width: 36px; height: 36px; }

.shell-main {
    flex: 1 1 auto;
    position: relative;
}

.shell-error {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 4px 16px 12px;
    padding: 10px 14px;
    border-radius: var(--radius-md);
    border: 1px solid color-mix(in srgb, var(--color-destructive) 40%, var(--color-separator));
    font-size: 13px;
    line-height: 18px;
    color: var(--color-text);
}
.shell-error__dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: var(--color-destructive);
    flex-shrink: 0;
}
.shell-error__msg { flex: 1 1 auto; }
.shell-error__close {
    font-size: 13px;
    font-weight: 500;
    color: var(--color-accent);
}
</style>
