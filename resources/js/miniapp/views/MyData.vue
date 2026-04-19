<script setup>
import { ref, computed, onMounted } from 'vue';

import { store, fetchMyData, deleteMyData, exportMyData, toast } from '../store.js';
import { t } from '../i18n.js';
import { hap } from '../telegram.js';

import { SectionLabel, ListGroup, ListRow, Button, Card, Sheet } from '../components/ui/index.js';
import DataRow from '../components/DataRow.vue';
import Skeleton from '../components/Skeleton.vue';

const exporting = ref(false);
const showConfirm = ref(false);
const err = ref(null);

const data = computed(() => store.myData);
const loading = computed(() => store.myDataLoading);

onMounted(() => {
    if (!store.myData) fetchMyData({ page: 1 });
});

async function loadMore() {
    if (!data.value?.hasMore) return;
    await fetchMyData({ page: (data.value.page || 1) + 1 });
}

async function onExport() {
    exporting.value = true;
    try { await exportMyData(); }
    catch (e) { err.value = e?.message || 'export_failed'; }
    finally { exporting.value = false; }
}

function askDelete() {
    hap.select();
    showConfirm.value = true;
}

async function confirmDelete() {
    showConfirm.value = false;
    try {
        const resp = await deleteMyData();
        toast(t('profile.myDataDeleted', { n: resp?.tombstoned ?? 0 }), 'success');
    } catch (e) {
        err.value = e?.response?.data?.message || e?.message || 'delete_failed';
    }
}

function fmtDate(iso) {
    if (!iso) return '—';
    try {
        const d = new Date(iso);
        return d.toLocaleDateString();
    } catch (_) { return iso; }
}
</script>

<template>
    <div class="md">
        <section class="md__section">
            <SectionLabel>{{ t('profile.myDataTitle') }}</SectionLabel>

            <div v-if="loading && !data" class="md__skel">
                <Skeleton :height="120" />
                <Skeleton :height="56" />
                <Skeleton :height="56" />
            </div>

            <template v-else-if="data && data.totalSignals > 0">
                <Card padding="lg" class="md__stats">
                    <div class="md__grid">
                        <div class="md__tile">
                            <span class="md__num">{{ data.totalSignals }}</span>
                            <span class="md__lbl">{{ t('profile.myDataStatsSignals', { n: data.totalSignals }) }}</span>
                        </div>
                        <div class="md__tile">
                            <span class="md__num">{{ data.distinctUrls }}</span>
                            <span class="md__lbl">{{ t('profile.myDataStatsUrls', { n: data.distinctUrls }) }}</span>
                        </div>
                        <div class="md__tile">
                            <span class="md__num">{{ data.distinctNetworks }}</span>
                            <span class="md__lbl">{{ t('profile.myDataStatsNetworks', { n: data.distinctNetworks }) }}</span>
                        </div>
                        <div class="md__tile">
                            <span class="md__num md__num--success">{{ data.reachableCount }}</span>
                            <span class="md__lbl">{{ t('profile.myDataStatsReachable', { n: data.reachableCount }) }}</span>
                        </div>
                        <div class="md__tile">
                            <span class="md__num md__num--danger">{{ data.blockedCount }}</span>
                            <span class="md__lbl">{{ t('profile.myDataStatsBlocked', { n: data.blockedCount }) }}</span>
                        </div>
                    </div>
                    <p class="md__range">
                        {{ t('profile.myDataRange', { from: fmtDate(data.firstSeenAt), to: fmtDate(data.lastSeenAt) }) }}
                    </p>
                </Card>

                <ListGroup>
                    <DataRow
                        v-for="sig in data.recentSignals"
                        :key="sig.id"
                        :signal="sig"
                    />
                    <ListRow v-if="data.hasMore" chevron @click="loadMore">
                        <template #title>{{ t('profile.myDataLoadMore') }}</template>
                    </ListRow>
                </ListGroup>

                <div class="md__actions">
                    <Button variant="ghost" :disabled="exporting" @click="onExport">
                        {{ exporting ? t('profile.myDataExporting') : t('profile.myDataExport') }}
                    </Button>
                    <ListGroup>
                        <ListRow destructive @click="askDelete">
                            <template #title>{{ t('profile.myDataDelete') }}</template>
                        </ListRow>
                    </ListGroup>
                </div>
            </template>

            <p v-else class="md__empty">{{ t('profile.myDataEmpty') }}</p>

            <p v-if="err" class="md__err">{{ err }}</p>
        </section>

        <Sheet v-model:open="showConfirm" :aria-label="t('profile.myDataDeleteConfirmTitle')">
            <h3 class="md__confirm-title">{{ t('profile.myDataDeleteConfirmTitle') }}</h3>
            <p class="md__confirm-body">{{ t('profile.myDataDeleteConfirmBody') }}</p>
            <div class="md__confirm-actions">
                <Button variant="ghost" @click="showConfirm = false">{{ t('common.cancel') }}</Button>
                <Button variant="destructive" @click="confirmDelete">{{ t('profile.myDataDeleteConfirm') }}</Button>
            </div>
        </Sheet>
    </div>
</template>

<style scoped>
.md { padding-bottom: 32px; }
.md__section { display: flex; flex-direction: column; gap: 12px; }
.md__skel { display: flex; flex-direction: column; gap: 12px; padding: 0 16px; }
.md__stats { margin: 0 16px; }
.md__grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
}
.md__tile {
    display: flex;
    flex-direction: column;
    gap: 2px;
}
.md__num {
    font-family: var(--font-serif, 'Instrument Serif', Georgia, serif);
    font-size: 26px;
    line-height: 30px;
    font-weight: 400;
    color: var(--color-text-strong);
}
.md__num--success { color: var(--color-success); }
.md__num--danger  { color: var(--color-danger); }
.md__lbl {
    font-size: 11px;
    color: var(--color-text-hint);
    letter-spacing: 0.04em;
    text-transform: uppercase;
}
.md__range {
    margin: 10px 0 0;
    font-size: 12px;
    color: var(--color-text-hint);
}
.md__actions {
    display: flex;
    flex-direction: column;
    gap: 12px;
    padding: 0 16px;
}
.md__empty {
    margin: 8px 0 0;
    padding: 0 16px;
    font-size: 13px;
    color: var(--color-text-subtle);
}
.md__err {
    margin: 0;
    padding: 0 16px;
    font-size: 12px;
    color: var(--color-danger);
}
.md__confirm-title {
    margin: 0 0 8px;
    font-family: var(--font-serif, 'Instrument Serif', Georgia, serif);
    font-weight: 400;
    font-size: 22px;
    line-height: 28px;
    color: var(--color-text-strong);
}
.md__confirm-body {
    margin: 0 0 16px;
    font-size: 14px;
    line-height: 20px;
    color: var(--color-text-subtle);
}
.md__confirm-actions {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
}
</style>
