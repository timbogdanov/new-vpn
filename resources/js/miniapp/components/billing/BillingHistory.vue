<script setup>
import { onMounted, ref } from 'vue';
import { fetchHistory } from '../../billing.js';
import { t } from '../../i18n.js';
import { EmptyState } from '../ui/index.js';
import StarsAmount from './StarsAmount.vue';

const items = ref([]);
const loading = ref(true);

onMounted(async () => {
    try {
        const all = await fetchHistory();
        items.value = (all || []).filter((p) => p.status !== 'pending');
    } finally {
        loading.value = false;
    }
});

function fmtDate(iso) {
    if (!iso) return '';
    try {
        return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium', timeStyle: 'short' }).format(new Date(iso));
    } catch (_) {
        return iso;
    }
}
</script>

<template>
    <div class="bill-history">
        <ul v-if="items.length" class="bill-history__list">
            <li v-for="p in items" :key="p.id" class="bill-history__row">
                <div class="bill-history__main">
                    <p class="bill-history__plan">{{ t('billing.plans.' + p.planKey + '.name') }}</p>
                    <p class="bill-history__date">{{ fmtDate(p.paidAt || p.createdAt) }}</p>
                </div>
                <div class="bill-history__right">
                    <StarsAmount :stars="p.stars" />
                    <span class="bill-history__status" :class="`bill-history__status--${p.status}`">{{ p.status }}</span>
                </div>
            </li>
        </ul>
        <EmptyState v-else-if="!loading" :title="t('billing.noHistory')" />
    </div>
</template>

<style scoped>
.bill-history__list {
    list-style: none;
    margin: 0;
    padding: 0;
    display: flex;
    flex-direction: column;
    background: var(--color-surface);
    border-radius: var(--radius-lg);
    overflow: hidden;
}
.bill-history__row {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 14px 16px;
}
.bill-history__row + .bill-history__row {
    border-top: 1px solid var(--color-separator);
}
.bill-history__main { flex: 1; min-width: 0; }
.bill-history__plan {
    font-size: 15px;
    line-height: 20px;
    font-weight: 500;
    color: var(--color-text-strong);
    margin: 0;
}
.bill-history__date {
    font-size: 12px;
    line-height: 16px;
    color: var(--color-text-hint);
    margin: 2px 0 0;
}
.bill-history__right {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 4px;
}
.bill-history__status {
    font-size: 11px;
    line-height: 14px;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--color-text-hint);
    font-weight: 600;
}
.bill-history__status--paid { color: var(--color-success); }
.bill-history__status--refunded { color: var(--color-warning); }
.bill-history__status--failed { color: var(--color-danger); }
</style>
