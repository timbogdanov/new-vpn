<script setup>
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';

import { searchOoni, store } from '../store.js';
import { t } from '../i18n.js';
import { hap } from '../telegram.js';

import { SectionLabel, ListGroup, ListRow, Chip } from '../components/ui/index.js';
import UrlSearchInput from '../components/UrlSearchInput.vue';

const router = useRouter();

const query = ref('');
const results = ref([]);
const loading = ref(false);
const err = ref(null);

const recents = ref([]);
const RECENTS_KEY = 'ooni:recentSearches';

onMounted(() => {
    try {
        const raw = window.localStorage.getItem(RECENTS_KEY);
        recents.value = raw ? JSON.parse(raw) : [];
    } catch (_) { recents.value = []; }
});

function pushRecent(item) {
    if (!item?.url) return;
    const next = [item, ...recents.value.filter((r) => r.url !== item.url)].slice(0, 8);
    recents.value = next;
    try { window.localStorage.setItem(RECENTS_KEY, JSON.stringify(next)); } catch (_) {}
}

async function onSearch(q) {
    err.value = null;
    if (!q) { results.value = []; return; }
    loading.value = true;
    try {
        const country = store.ooni?.countryCode || null;
        results.value = await searchOoni(q, { country });
    } catch (e) {
        err.value = e?.response?.data?.message || e?.message || 'search_failed';
    } finally {
        loading.value = false;
    }
}

function pick(item) {
    if (!item?.url) return;
    hap.select();
    pushRecent(item);
    router.push({ name: 'freedom-url-detail', params: { urlHash: item.urlHash }, query: { url: item.url } });
}

function toneForSource(source) {
    if (source === 'catalog') return 'accent';
    if (source === 'seed') return 'neutral';
    if (source === 'observed') return 'success';
    return 'neutral';
}

const showRecents = computed(() => !query.value && recents.value.length > 0);
const showEmpty = computed(() => query.value && !loading.value && !results.value.length);
</script>

<template>
    <div class="fs">
        <section class="fs__section">
            <SectionLabel>{{ t('tools.freedomSearchTitle') }}</SectionLabel>
            <UrlSearchInput
                v-model="query"
                :loading="loading"
                @search="onSearch"
            />

            <div v-if="loading && !results.length" class="fs__hint">{{ t('tools.freedomSearchLoading') }}</div>

            <ListGroup v-if="results.length">
                <ListRow
                    v-for="r in results"
                    :key="r.url"
                    chevron
                    @click="pick(r)"
                >
                    <template #title>{{ r.label || r.host }}</template>
                    <template #subtitle>{{ r.url }}</template>
                    <template #trailing>
                        <Chip :tone="toneForSource(r.source)">{{ r.source }}</Chip>
                    </template>
                </ListRow>
            </ListGroup>

            <p v-if="showEmpty" class="fs__empty">{{ t('tools.freedomSearchEmpty') }}</p>

            <template v-if="showRecents">
                <SectionLabel>{{ t('tools.freedomSearchRecents') }}</SectionLabel>
                <ListGroup>
                    <ListRow
                        v-for="r in recents"
                        :key="r.url"
                        chevron
                        @click="pick(r)"
                    >
                        <template #title>{{ r.label || r.host }}</template>
                        <template #subtitle>{{ r.url }}</template>
                    </ListRow>
                </ListGroup>
            </template>

            <p v-if="err" class="fs__err">{{ err }}</p>
        </section>
    </div>
</template>

<style scoped>
.fs { display: flex; flex-direction: column; gap: 20px; padding-bottom: 32px; }
.fs__section { display: flex; flex-direction: column; gap: 10px; }
.fs__hint {
    padding: 0 16px;
    font-size: 12px;
    color: var(--color-text-hint);
}
.fs__empty {
    margin: 8px 0 0;
    padding: 0 16px;
    font-size: 13px;
    color: var(--color-text-subtle);
}
.fs__err {
    margin: 0;
    padding: 0 16px;
    font-size: 12px;
    color: var(--color-danger);
}
</style>
