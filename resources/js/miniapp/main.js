import { createApp, watch } from 'vue';
import { router } from './router.js';
import { installTelegramBridges, webApp } from './telegram.js';
import { bootstrap, store } from './store.js';
import { i18nState, setLocale } from './i18n.js';
import AppShell from './components/AppShell.vue';

installTelegramBridges();

try { webApp.ready(); } catch (_) {}
try { webApp.expand(); } catch (_) {}
try { webApp.setHeaderColor && webApp.setHeaderColor('bg_color'); } catch (_) {}
try { webApp.setBackgroundColor && webApp.setBackgroundColor('bg_color'); } catch (_) {}

// Keep i18n locale in sync with whatever the backend has on file for the user.
watch(
    () => store.user?.languageCode,
    (code) => {
        if (code) setLocale(code);
    },
    { immediate: true },
);

window.addEventListener('miniapp:unauthorized', () => {
    try { webApp.showAlert('Session expired. Please reopen the app.', () => webApp.close()); } catch (_) {}
});

const app = createApp(AppShell);
app.use(router);

// Global error boundary — never show a white screen inside Telegram.
app.config.errorHandler = (err, _vm, info) => {
    console.error('[miniapp]', info, err);
    store.error = (err && err.message) || String(err);
};

app.mount('#app');

// Kick off bootstrap after mount so the skeleton renders first.
bootstrap().then(() => {
    if (store.user?.languageCode) setLocale(store.user.languageCode);
});

// Expose for debugging in desktop web dev.
if (import.meta.env.DEV) {
    window.__miniapp = { store, router, i18nState };
}
