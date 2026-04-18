import { reactive, computed } from 'vue';
import api from './api.js';
import { t } from './i18n.js';

export const store = reactive({
    ready: false,
    loading: false,
    error: null,

    user: null,
    servers: [],
    config: {},
    profile: null,
    subscription: null,
    announcements: [],

    connectCache: {},
    toast: null,

    get availableServers() {
        return this.servers.filter((s) => !s.isComingSoon);
    },
    get comingSoonServers() {
        return this.servers.filter((s) => s.isComingSoon);
    },
    get recommendedServer() {
        const pool = this.availableServers;
        if (!pool.length) return null;
        return [...pool].sort((a, b) => (a.loadPercent ?? 100) - (b.loadPercent ?? 100))[0];
    },
});

export const translatedLanguage = computed(() => store.user?.languageCode || 'ru');

export async function bootstrap() {
    store.loading = true;
    store.error = null;
    try {
        const { data } = await api.get('/bootstrap');
        store.user = data.user;
        store.servers = data.servers || [];
        store.config = data.config || {};
        store.subscription = data.subscription || null;
        store.ready = true;
    } catch (e) {
        store.error = describeError(e);
    } finally {
        store.loading = false;
    }
}

export async function markOnboarded() {
    if (store.user?.onboardedAt) return;
    try {
        const { data } = await api.patch('/profile', { onboarded: true });
        if (data.user?.onboardedAt && store.user) {
            store.user.onboardedAt = data.user.onboardedAt;
        }
    } catch (_) {}
}

export async function refreshServers() {
    try {
        const { data } = await api.get('/servers');
        store.servers = data.servers || [];
    } catch (e) {
        store.error = describeError(e);
    }
}

export async function connect(slug) {
    const existing = store.connectCache[slug];
    if (existing && Date.now() - existing.fetchedAt < 60_000) {
        return existing.payload;
    }
    const { data } = await api.post(`/servers/${slug}/connect`);
    store.connectCache[slug] = { payload: data, fetchedAt: Date.now() };
    return data;
}

export async function fetchProfile() {
    const { data } = await api.get('/profile');
    store.profile = data;
    return data;
}

export async function updateProfile(patch) {
    const { data } = await api.patch('/profile', patch);
    if (store.user) {
        if (data.user?.languageCode) store.user.languageCode = data.user.languageCode;
        if (data.user?.subToken) store.user.subToken = data.user.subToken;
    }
    if (data.aggregatedSubscriptionUrl && store.config) {
        store.config.aggregatedSubscriptionUrl = data.aggregatedSubscriptionUrl;
    }
    return data;
}

export async function runIpCheck() {
    const { data } = await api.post('/tools/ip-check');
    return data.result;
}

export async function runSpeedTest() {
    const { data } = await api.post('/tools/speed-test');
    return data.result;
}

export function toast(message, kind = 'info', ms = 2400) {
    store.toast = { message, kind, id: Date.now() };
    setTimeout(() => {
        if (store.toast && store.toast.message === message) store.toast = null;
    }, ms);
}

function describeError(e) {
    if (!e) return t('common.error');
    if (e.response?.data?.message) return e.response.data.message;
    if (e.response?.status === 401) return t('common.sessionExpired');
    if (e.message) return e.message;
    return t('common.error');
}
