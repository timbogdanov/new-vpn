import { reactive } from 'vue';

const tg = window.Telegram && window.Telegram.WebApp ? window.Telegram.WebApp : null;

// A narrow shim when running outside Telegram (e.g. desktop browser dev).
const shim = {
    initData: '',
    initDataUnsafe: {},
    platform: 'unknown',
    version: '0',
    colorScheme: 'dark',
    themeParams: {},
    viewportHeight: window.innerHeight,
    viewportStableHeight: window.innerHeight,
    isExpanded: true,
    MainButton: {
        text: '',
        isVisible: false,
        isActive: true,
        setText() {}, show() {}, hide() {}, enable() {}, disable() {},
        showProgress() {}, hideProgress() {},
        onClick() {}, offClick() {},
    },
    BackButton: {
        isVisible: false,
        show() {}, hide() {},
        onClick() {}, offClick() {},
    },
    HapticFeedback: {
        impactOccurred() {}, notificationOccurred() {}, selectionChanged() {},
    },
    ready() {}, expand() {}, close() {},
    openLink(url) { try { window.open(url, '_blank', 'noopener'); } catch (_) {} },
    openTelegramLink(url) { try { window.open(url, '_blank', 'noopener'); } catch (_) {} },
    showPopup(_p, cb) { cb && cb(); },
    showAlert(msg, cb) { window.alert(msg); cb && cb(); },
    showConfirm(msg, cb) { cb && cb(window.confirm(msg)); },
    onEvent() {}, offEvent() {},
    setHeaderColor() {}, setBackgroundColor() {},
    enableClosingConfirmation() {}, disableClosingConfirmation() {},
    isVersionAtLeast() { return false; },
};

export const webApp = tg || shim;

export const isRealTelegram = !!tg;

export const tgState = reactive({
    platform: webApp.platform || 'unknown',
    colorScheme: webApp.colorScheme || 'dark',
    viewportStableHeight: webApp.viewportStableHeight || window.innerHeight,
    isExpanded: !!webApp.isExpanded,
});

// Bridge Telegram events → reactive state + CSS vars
export function installTelegramBridges() {
    if (!isRealTelegram) return;

    const syncTheme = () => {
        const tp = webApp.themeParams || {};
        const root = document.documentElement;
        const map = {
            bg_color: '--tg-theme-bg-color',
            text_color: '--tg-theme-text-color',
            hint_color: '--tg-theme-hint-color',
            link_color: '--tg-theme-link-color',
            button_color: '--tg-theme-button-color',
            button_text_color: '--tg-theme-button-text-color',
            secondary_bg_color: '--tg-theme-secondary-bg-color',
            header_bg_color: '--tg-theme-header-bg-color',
            bottom_bar_bg_color: '--tg-theme-bottom-bar-bg-color',
            accent_text_color: '--tg-theme-accent-text-color',
            section_bg_color: '--tg-theme-section-bg-color',
            section_header_text_color: '--tg-theme-section-header-text-color',
            section_separator_color: '--tg-theme-section-separator-color',
            subtitle_text_color: '--tg-theme-subtitle-text-color',
            destructive_text_color: '--tg-theme-destructive-text-color',
        };
        for (const k in map) {
            if (tp[k]) root.style.setProperty(map[k], tp[k]);
        }
        root.setAttribute('data-theme', webApp.colorScheme || 'dark');
        tgState.colorScheme = webApp.colorScheme || 'dark';

        // If Telegram gives us a low-contrast hint colour, fall back to our
        // local gray-6 so readability never collapses on exotic user themes.
        enforceHintContrast(tp.hint_color, tp.bg_color);
    };

    const syncViewport = () => {
        const h = webApp.viewportStableHeight || window.innerHeight;
        document.documentElement.style.setProperty('--tg-viewport-stable-height', h + 'px');
        tgState.viewportStableHeight = h;
        tgState.isExpanded = !!webApp.isExpanded;
    };

    webApp.onEvent('themeChanged', syncTheme);
    webApp.onEvent('viewportChanged', syncViewport);

    syncTheme();
    syncViewport();
}

// Haptic feedback helpers (safe no-ops outside Telegram).
export const hap = {
    light: () => safeHaptic('impactOccurred', 'light'),
    medium: () => safeHaptic('impactOccurred', 'medium'),
    heavy: () => safeHaptic('impactOccurred', 'heavy'),
    rigid: () => safeHaptic('impactOccurred', 'rigid'),
    soft: () => safeHaptic('impactOccurred', 'soft'),
    select: () => safeHaptic('selectionChanged'),
    success: () => safeHaptic('notificationOccurred', 'success'),
    warning: () => safeHaptic('notificationOccurred', 'warning'),
    error: () => safeHaptic('notificationOccurred', 'error'),
};

function safeHaptic(method, arg) {
    try {
        const hf = webApp.HapticFeedback;
        if (!hf || typeof hf[method] !== 'function') return;
        if (arg !== undefined) hf[method](arg);
        else hf[method]();
    } catch (_) {}
}

// Device classification: iOS / Android / macOS / desktop-web.
export function detectDevice() {
    const p = (webApp.platform || '').toLowerCase();
    if (p === 'ios') return 'ios';
    if (p === 'macos') return 'macos';
    if (p === 'android') return 'android';
    if (p === 'tdesktop' || p.startsWith('web')) return 'desktop';

    // UA fallback for non-Telegram dev or "unknown" platform value.
    const ua = navigator.userAgent || '';
    if (/iPhone|iPod/.test(ua)) return 'ios';
    if (/iPad|Macintosh/.test(ua) && 'ontouchend' in document) return 'ios';
    if (/Mac OS X/.test(ua)) return 'macos';
    if (/Android/.test(ua)) return 'android';
    return 'desktop';
}

// Try to launch a VPN app deep-link. Telegram's openLink() is best on iOS/Android.
// Falls back to a direct location assignment on desktop-web where custom schemes
// are usually blocked but we still need to try.
export function openDeepLink(url) {
    if (!url) return;
    try {
        if (isRealTelegram && typeof webApp.openLink === 'function') {
            webApp.openLink(url);
            return;
        }
    } catch (_) {}
    try { window.location.href = url; } catch (_) {}
}

// External HTTPS links (App Store, docs). Prefer openLink so the user doesn't lose the Mini App.
export function openExternal(url) {
    if (!url) return;
    try {
        if (isRealTelegram && typeof webApp.openLink === 'function') {
            webApp.openLink(url, { try_instant_view: false });
            return;
        }
    } catch (_) {}
    try { window.open(url, '_blank', 'noopener'); } catch (_) {}
}

// MainButton helpers with reference counting for router convenience.
export const MainButton = {
    show(text, onClick, opts = {}) {
        try {
            webApp.MainButton.setText(text);
            if (opts.color) webApp.MainButton.color = opts.color;
            if (opts.textColor) webApp.MainButton.textColor = opts.textColor;
            webApp.MainButton.onClick(onClick);
            webApp.MainButton.show();
        } catch (_) {}
        return () => this.hide(onClick);
    },
    hide(onClick) {
        try {
            webApp.MainButton.hide();
            if (onClick) webApp.MainButton.offClick(onClick);
        } catch (_) {}
    },
    setProgress(on) {
        try { on ? webApp.MainButton.showProgress(true) : webApp.MainButton.hideProgress(); } catch (_) {}
    },
};

export const BackButton = {
    show(onClick) {
        try {
            webApp.BackButton.onClick(onClick);
            webApp.BackButton.show();
        } catch (_) {}
        return () => this.hide(onClick);
    },
    hide(onClick) {
        try {
            webApp.BackButton.hide();
            if (onClick) webApp.BackButton.offClick(onClick);
        } catch (_) {}
    },
};

function parseColor(input) {
    if (!input || typeof input !== 'string') return null;
    const s = input.trim();
    if (s.startsWith('#')) {
        const hex = s.length === 4
            ? s.slice(1).split('').map((c) => c + c).join('')
            : s.slice(1);
        if (hex.length !== 6) return null;
        const n = parseInt(hex, 16);
        if (Number.isNaN(n)) return null;
        return [(n >> 16) & 0xff, (n >> 8) & 0xff, n & 0xff];
    }
    const m = s.match(/rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i);
    if (m) return [+m[1], +m[2], +m[3]];
    return null;
}

function relLuminance([r, g, b]) {
    const chan = (c) => {
        const v = c / 255;
        return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4);
    };
    return 0.2126 * chan(r) + 0.7152 * chan(g) + 0.0722 * chan(b);
}

function contrastRatio(fg, bg) {
    if (!fg || !bg) return null;
    const l1 = relLuminance(fg);
    const l2 = relLuminance(bg);
    const [a, b] = l1 > l2 ? [l1, l2] : [l2, l1];
    return (a + 0.05) / (b + 0.05);
}

function enforceHintContrast(hintColor, bgColor) {
    const root = document.documentElement;
    const ratio = contrastRatio(parseColor(hintColor), parseColor(bgColor));
    if (ratio !== null && ratio < 3) {
        root.style.setProperty('--color-text-hint', 'var(--color-gray-6)');
    } else {
        root.style.removeProperty('--color-text-hint');
    }
}

export function showPopup(params) {
    return new Promise((resolve) => {
        try {
            if (webApp.showPopup && webApp.isVersionAtLeast && webApp.isVersionAtLeast('6.2')) {
                webApp.showPopup(params, (id) => resolve(id));
                return;
            }
        } catch (_) {}
        window.alert(params.message || '');
        resolve(null);
    });
}
