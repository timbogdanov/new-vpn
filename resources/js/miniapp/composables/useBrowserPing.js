import { ref } from 'vue';

/**
 * Measure round-trip latency from the user's browser to a given HTTPS host.
 * Uses no-CORS HEAD requests so we don't need the server to add CORS.
 */
export function useBrowserPing() {
    const pings = ref({});

    async function ping(slug, url, { samples = 2, timeoutMs = 2500 } = {}) {
        if (!url || !slug) return null;
        const results = [];
        for (let i = 0; i < samples; i++) {
            const ctrl = new AbortController();
            const timer = setTimeout(() => ctrl.abort(), timeoutMs);
            const start = performance.now();
            try {
                await fetch(url + '?_t=' + Date.now(), {
                    method: 'HEAD',
                    mode: 'no-cors',
                    cache: 'no-store',
                    signal: ctrl.signal,
                });
                results.push(performance.now() - start);
            } catch (_) {
                // ignored — aborted or network error
            } finally {
                clearTimeout(timer);
            }
        }
        if (!results.length) {
            pings.value = { ...pings.value, [slug]: null };
            return null;
        }
        const median = Math.round(results.sort((a, b) => a - b)[Math.floor(results.length / 2)]);
        pings.value = { ...pings.value, [slug]: median };
        return median;
    }

    return { pings, ping };
}
