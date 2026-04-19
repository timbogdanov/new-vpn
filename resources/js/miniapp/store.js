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
    ooniWatchlistUrls: [],

    urlDetailsCache: {},     // keyed by sha1(url)
    searchCache: {},         // keyed by `${q}|${country}`

    myData: null,
    myDataLoading: false,

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
        store.ooniWatchlistUrls = Array.isArray(data.user?.ooniWatchlistUrls) ? data.user.ooniWatchlistUrls : [];
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
        if (data.user && 'contributeSignals' in data.user) {
            store.user.contributeSignals = !!data.user.contributeSignals;
        }
        if (data.user?.contributeAckedAt) {
            store.user.contributeAckedAt = data.user.contributeAckedAt;
        }
    }
    if (data.aggregatedSubscriptionUrl && store.config) {
        store.config.aggregatedSubscriptionUrl = data.aggregatedSubscriptionUrl;
    }
    return data;
}

export async function ackContributeBanner() {
    // Optimistic: flip the local flag immediately so the computed that drives
    // the banner's :open becomes false on the next tick. If the API call
    // fails (e.g. ooni_contribute_acked_at column doesn't exist because the
    // migration hasn't run), the banner will reappear on next full reload —
    // but at least the user can dismiss it now.
    if (store.user && !store.user.contributeAckedAt) {
        store.user.contributeAckedAt = new Date().toISOString();
    }
    try {
        const { data } = await api.patch('/profile', { contributeAcked: true });
        if (store.user && data.user?.contributeAckedAt) {
            store.user.contributeAckedAt = data.user.contributeAckedAt;
        }
    } catch (e) {
        // Log so Telescope / server logs can catch it; do NOT rollback the
        // local ack — leaving the banner closed is better UX than re-opening.
        // eslint-disable-next-line no-console
        console.warn('ackContributeBanner failed', e);
    }
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
        store.ooniWatchlistUrls = Array.isArray(data?.urls) ? data.urls : [];
    } catch (_) { /* ignore */ }
    return { services: store.ooniWatchlist, urls: store.ooniWatchlistUrls };
}

export async function toggleOoniWatch(serviceKey) {
    if (!serviceKey) return;
    const cur = Array.isArray(store.ooniWatchlist) ? store.ooniWatchlist : [];
    const has = cur.includes(serviceKey);
    const next = has ? cur.filter((k) => k !== serviceKey) : [...cur, serviceKey];
    store.ooniWatchlist = next;
    try {
        const { data } = await api.put('/ooni/watchlist', {
            services: next,
            urls: store.ooniWatchlistUrls || [],
        });
        if (Array.isArray(data?.services)) store.ooniWatchlist = data.services;
        if (Array.isArray(data?.urls)) store.ooniWatchlistUrls = data.urls;
    } catch (e) {
        store.ooniWatchlist = cur;
        throw e;
    }
    return store.ooniWatchlist;
}

export async function toggleUrlWatch(url) {
    if (!url) return;
    const cur = Array.isArray(store.ooniWatchlistUrls) ? store.ooniWatchlistUrls : [];
    const has = cur.includes(url);
    const next = has ? cur.filter((u) => u !== url) : [...cur, url];
    store.ooniWatchlistUrls = next;
    try {
        const { data } = await api.put('/ooni/watchlist', {
            services: store.ooniWatchlist || [],
            urls: next,
        });
        if (Array.isArray(data?.services)) store.ooniWatchlist = data.services;
        if (Array.isArray(data?.urls)) store.ooniWatchlistUrls = data.urls;
    } catch (e) {
        store.ooniWatchlistUrls = cur;
        throw e;
    }
    return store.ooniWatchlistUrls;
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

export async function searchOoni(q, { country = null, limit = 10 } = {}) {
    const key = `${(q || '').toLowerCase()}|${country || ''}`;
    const cached = store.searchCache[key];
    if (cached && Date.now() - cached.fetchedAt < 5 * 60_000) {
        return cached.results;
    }
    const params = { q, limit };
    if (country) params.country = country;
    const { data } = await api.get('/ooni/search', { params });
    const results = Array.isArray(data?.results) ? data.results : [];
    store.searchCache[key] = { results, fetchedAt: Date.now() };
    return results;
}

export async function fetchUrlDetails(url, { force = false, country = null, asn = null, days = 30 } = {}) {
    if (!url) throw new Error('url required');
    const hash = hashFor(url);
    const cached = store.urlDetailsCache[hash];
    if (!force && cached && Date.now() - cached._fetchedAt < 15 * 60_000) {
        return cached;
    }
    const params = { url, days };
    if (force) params.force = 1;
    if (country) params.country = country;
    if (asn) params.asn = asn;
    const { data } = await api.get('/ooni/url-details', { params });
    const dto = { ...data.result, _fetchedAt: Date.now() };
    store.urlDetailsCache[hash] = dto;
    return dto;
}

export async function fetchMyData({ page = 1, perPage = 30 } = {}) {
    store.myDataLoading = true;
    try {
        const { data } = await api.get('/ooni/my-data', { params: { page, perPage } });
        if (page === 1) {
            store.myData = data;
        } else if (store.myData) {
            // Append for pagination
            store.myData = {
                ...data,
                recentSignals: [...store.myData.recentSignals, ...(data.recentSignals || [])],
            };
        }
        return data;
    } finally {
        store.myDataLoading = false;
    }
}

export async function deleteMyData() {
    const { data } = await api.delete('/ooni/my-data', { params: { confirm: 1 } });
    store.myData = {
        totalSignals: 0,
        firstSeenAt: null,
        lastSeenAt: null,
        distinctUrls: 0,
        reachableCount: 0,
        blockedCount: 0,
        distinctNetworks: 0,
        recentSignals: [],
        page: 1,
        perPage: 30,
        totalPages: 0,
        hasMore: false,
    };
    return data;
}

export function exportMyDataUrl() {
    // Using a signed initData dance for downloads is awkward; the simplest
    // reliable approach is an XHR fetch with our normal interceptor then an
    // in-memory blob download.
    return '/api/miniapp/ooni/my-data/export';
}

export async function exportMyData() {
    const response = await api.get('/ooni/my-data/export', { responseType: 'blob' });
    const blob = response.data instanceof Blob ? response.data : new Blob([response.data], { type: 'application/json' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `larastory-ooni-data-${store.user?.id ?? 'export'}.json`;
    document.body.appendChild(a);
    a.click();
    a.remove();
    window.URL.revokeObjectURL(url);
}

export function toast(message, kind = 'info', ms = 2400) {
    store.toast = { message, kind, id: Date.now() };
    setTimeout(() => {
        if (store.toast && store.toast.message === message) store.toast = null;
    }, ms);
}

// Tiny SHA-1 replacement: we only need a stable cache key for URL lookups.
// The backend returns `urlHash` on details, so prefer that where available.
function hashFor(url) {
    let h = 0;
    for (let i = 0; i < url.length; i++) {
        h = ((h << 5) - h + url.charCodeAt(i)) | 0;
    }
    return 'u' + (h >>> 0).toString(36);
}

function describeError(e) {
    if (!e) return t('common.error');
    if (e.response?.data?.message) return e.response.data.message;
    if (e.response?.status === 401) return t('common.sessionExpired');
    if (e.message) return e.message;
    return t('common.error');
}
