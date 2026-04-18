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
        case 'server-detail': return store.servers.find(s => s.slug === route.params.slug)?.name || '';
        default: return '';
    }
});

function goHome() {
    hap.select();
    router.push('/');
}

function goProfile() {
    hap.select();
    router.push('/profile');
}
</script>

<template>
    <div class="min-h-[var(--tg-viewport-stable-height,100vh)] flex flex-col">
        <header
            v-if="route.name !== 'home'"
            class="sticky top-0 z-20 px-4 pt-safe pb-3 flex items-center justify-between"
            style="background: linear-gradient(180deg, var(--color-bg) 65%, transparent);"
        >
            <button class="w-10 h-10 rounded-full flex items-center justify-center -ml-2" @click="goHome">
                <span aria-hidden="true" style="font-size:20px">☰</span>
            </button>
            <div class="text-base font-semibold tracking-tight">{{ title }}</div>
            <div class="w-10 h-10" />
        </header>

        <header
            v-else
            class="px-5 pt-safe pb-2 flex items-center justify-between"
        >
            <div class="flex items-center gap-3 min-w-0">
                <div class="w-10 h-10 rounded-full overflow-hidden flex items-center justify-center"
                     style="background: var(--color-bg-section);">
                    <img
                        v-if="store.user?.photoUrl"
                        :src="store.user.photoUrl"
                        :alt="store.user.displayName"
                        class="w-full h-full object-cover"
                        referrerpolicy="no-referrer"
                    >
                    <span v-else style="font-size:18px">👤</span>
                </div>
                <div class="min-w-0">
                    <div class="text-xs" style="color: var(--color-text-hint)">{{ t('home.subtitle') }}</div>
                    <div class="truncate text-[15px] font-semibold">
                        {{ t('home.greeting', { name: store.user?.firstName || '…' }) }}
                    </div>
                </div>
            </div>
            <button class="w-10 h-10 rounded-full flex items-center justify-center"
                    style="background: var(--color-bg-section)" @click="goProfile">
                <span aria-hidden="true" style="font-size:16px">⚙</span>
            </button>
        </header>

        <main class="flex-1 relative px-4 pb-safe">
            <div v-if="store.error"
                 class="mx-1 my-2 card px-4 py-3 flex items-start gap-3"
                 style="border-color: color-mix(in srgb, var(--color-destructive) 40%, transparent)">
                <span style="color: var(--color-destructive)">●</span>
                <div class="flex-1 text-sm" style="color: var(--color-text)">{{ store.error }}</div>
                <button class="text-sm font-medium" style="color: var(--color-text-accent)"
                        @click="store.error = null">{{ t('common.close') }}</button>
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
