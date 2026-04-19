<script setup>
import { onMounted, ref } from 'vue';
import { fetchHistory } from '../../billing.js';
import { t } from '../../i18n.js';
import { EmptyState, ListGroup, ListRow } from '../ui/index.js';

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
        return new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' }).format(new Date(iso));
    } catch (_) {
        return iso;
    }
}

function statusLabel(status) {
    const key = `billing.status.${status}`;
    const txt = t(key);
    return txt === key ? status : txt;
}
</script>

<template>
    <div class="bill-history">
        <ListGroup v-if="items.length">
            <ListRow
                v-for="p in items"
                :key="p.id"
                :interactive="false"
            >
                <template #title>{{ t('billing.plans.' + p.planKey + '.name') }}</template>
                <template #subtitle>{{ fmtDate(p.paidAt || p.createdAt) }}</template>
                <template #trailing>
                    <span class="bill-history__stars tabular-nums">★ {{ new Intl.NumberFormat().format(p.stars) }}</span>
                    <span
                        v-if="p.status && p.status !== 'paid'"
                        class="bill-history__status"
                        :class="`bill-history__status--${p.status}`"
                    >{{ statusLabel(p.status) }}</span>
                </template>
            </ListRow>
        </ListGroup>
        <EmptyState v-else-if="!loading" :title="t('billing.noHistory')" />
    </div>
</template>

<style scoped>
.bill-history {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.bill-history__stars {
    font-family: var(--font-mono);
    font-size: 13px;
    font-weight: 500;
    color: var(--color-text-strong);
}

.bill-history__status {
    font-size: 11px;
    line-height: 14px;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--color-text-hint);
    font-weight: 600;
}
.bill-history__status--refunded { color: var(--color-warning); }
.bill-history__status--failed { color: var(--color-danger); }
</style>
