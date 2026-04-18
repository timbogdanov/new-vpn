import api from './api.js';
import { webApp, hap } from './telegram.js';
import { store, toast } from './store.js';
import { t } from './i18n.js';

export async function fetchPlans() {
    const { data } = await api.get('/billing/plans');
    return data.plans || [];
}

export async function fetchHistory() {
    const { data } = await api.get('/billing/history');
    return data.payments || [];
}

export async function fetchActiveAnnouncements() {
    try {
        const { data } = await api.get('/announcements/active');
        return data.announcements || [];
    } catch (_) {
        return [];
    }
}

/**
 * Trigger Stars checkout. Resolves with the openInvoice status string:
 *   'paid' | 'cancelled' | 'failed' | 'pending'.
 *
 * On 'paid' we re-fetch bootstrap so the new subscription state lands in the
 * store immediately. The webhook does the real activation server-side; this
 * is just UI cache invalidation.
 */
export async function buyPlan(planKey, { onActivated } = {}) {
    let invoice;
    try {
        const { data } = await api.post('/billing/invoice', { planKey });
        invoice = data;
    } catch (e) {
        const msg = e.response?.data?.message || t('billing.errors.unknown');
        toast(msg, 'error');
        hap.error();
        throw e;
    }

    if (!invoice?.invoiceLink) {
        toast(t('billing.errors.unknown'), 'error');
        throw new Error('No invoice link');
    }

    return new Promise((resolve) => {
        toast(t('billing.paying'), 'info');

        const wa = webApp;
        if (!wa || typeof wa.openInvoice !== 'function') {
            // Browser dev fallback: just open the link.
            try { window.open(invoice.invoiceLink, '_blank', 'noopener'); } catch (_) {}
            resolve('pending');
            return;
        }

        try {
            wa.openInvoice(invoice.invoiceLink, async (status) => {
                if (status === 'paid') {
                    hap.success();
                    try {
                        const { bootstrap } = await import('./store.js');
                        await bootstrap();
                    } catch (_) {}
                    if (typeof onActivated === 'function') onActivated();
                } else if (status === 'failed') {
                    hap.error();
                    toast(t('billing.errors.paymentFailed'), 'error');
                } else if (status === 'cancelled') {
                    hap.warning();
                }
                resolve(status);
            });
        } catch (e) {
            toast(t('billing.errors.unknown'), 'error');
            resolve('failed');
        }
    });
}

export function planByKey(key) {
    return (store.subscription?.plans || []).find((p) => p.key === key) || null;
}

export function activeSubscription() {
    return store.subscription?.active || null;
}

export function isTrialing() {
    return activeSubscription()?.isTrial === true;
}

export function isExpired() {
    const a = activeSubscription();
    if (!a) return true;
    if (!a.expiresAt) return false;
    return new Date(a.expiresAt).getTime() <= Date.now();
}

export function hoursUntilExpiry() {
    const a = activeSubscription();
    if (!a?.expiresAt) return Infinity;
    const ms = new Date(a.expiresAt).getTime() - Date.now();
    return Math.max(0, Math.round(ms / 3_600_000));
}

export function daysUntilExpiry() {
    const h = hoursUntilExpiry();
    if (h === Infinity) return Infinity;
    return Math.max(0, Math.ceil(h / 24));
}
