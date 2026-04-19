// Webview-side reachability probe. Uses opaque no-cors fetch: success or redirect
// means the origin resolved and accepted a TCP+TLS handshake from this device;
// any network error / abort means it didn't. This is the only way to measure
// arbitrary-domain reachability from a Telegram Mini App without server help.

export async function probeReachability(url, timeoutMs = 5000) {
    const ctrl = new AbortController();
    const to = setTimeout(() => ctrl.abort(), timeoutMs);
    try {
        await fetch(url, {
            mode: 'no-cors',
            cache: 'no-store',
            redirect: 'follow',
            signal: ctrl.signal,
        });
        return 'reachable';
    } catch (_) {
        return 'blocked';
    } finally {
        clearTimeout(to);
    }
}
