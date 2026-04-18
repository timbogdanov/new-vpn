import { computed, reactive } from 'vue';
import en from './locales/en.json';
import ru from './locales/ru.json';

const bundles = { en, ru };

export const i18nState = reactive({
    locale: 'ru',
});

export function setLocale(code) {
    if (bundles[code]) {
        i18nState.locale = code;
        try { document.documentElement.lang = code; } catch (_) {}
    }
}

export function t(key, vars) {
    const bundle = bundles[i18nState.locale] || bundles.ru;
    let str = resolvePath(bundle, key) || resolvePath(bundles.en, key) || key;
    if (vars && typeof str === 'string') {
        for (const k in vars) {
            str = str.replaceAll(`{${k}}`, String(vars[k]));
        }
    }
    return str;
}

function resolvePath(obj, path) {
    const parts = path.split('.');
    let node = obj;
    for (const p of parts) {
        if (node && Object.prototype.hasOwnProperty.call(node, p)) {
            node = node[p];
        } else {
            return null;
        }
    }
    return node;
}

export const tt = (key, vars) => computed(() => t(key, vars));
