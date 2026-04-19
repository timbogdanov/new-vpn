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

    ooni: null,
    ooniLoading: false,
    ooniWatchlist: [],

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
        store.ooniWatchlist = Array.isArray(data.user?.ooniWatchlist) ? data.user.ooniWatchlist : [];
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

export async function fetchOoniWatchlist() {
    try {
        const { data } = await api.get('/ooni/watchlist');
        store.ooniWatchlist = Array.isArray(data?.services) ? data.services : [];
    } catch (_) { /* ignore */ }
    return store.ooniWatchlist;
}

export async function toggleOoniWatch(serviceKey) {
    if (!serviceKey) return;
    const cur = Array.isArray(store.ooniWatchlist) ? store.ooniWatchlist : [];
    const has = cur.includes(serviceKey);
    const next = has ? cur.filter((k) => k !== serviceKey) : [...cur, serviceKey];
    // Optimistic update
    store.ooniWatchlist = next;
    try {
        const { data } = await api.put('/ooni/watchlist', { services: next });
        if (Array.isArray(data?.services)) {
            store.ooniWatchlist = data.services;
        }
    } catch (e) {
        // Rollback on failure
        store.ooniWatchlist = cur;
        throw e;
    }
    return store.ooniWatchlist;
}

export async function contributeSignals({ country, asn = null, signals }) {
    if (!store.user?.contributeSignals) return;
    if (!country || !Array.isArray(signals) || !signals.length) return;
    try {
        await api.post('/ooni/contribute', { country, asn, signals });
    } catch (_) { /* fire-and-forget */ }
}

export async function fetchOoniSummary({ force = false } = {}) {
    if (!force && store.ooni && (Date.now() - (store.ooni._fetchedAt || 0) < 15 * 60_000)) {
        return store.ooni;
    }
    store.ooniLoading = true;
    try {
        const { data } = await api.get('/tools/ooni-summary', {
            params: force ? { force: 1 } : {},
        });
        store.ooni = { ...data.result, _fetchedAt: Date.now() };
        return store.ooni;
    } catch (e) {
        store.ooni = null;
        throw e;
    } finally {
        store.ooniLoading = false;
    }
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
