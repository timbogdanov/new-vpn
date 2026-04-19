<script setup>
import { ListRow, Chip } from './ui/index.js';

const props = defineProps({
    signal: { type: Object, required: true },
});

function tone(result) {
    if (result === 'reachable') return 'success';
    if (result === 'blocked') return 'danger';
    return 'neutral';
}

function relTime(iso) {
    if (!iso) return '';
    const t = Date.parse(iso);
    if (Number.isNaN(t)) return iso;
    const delta = Math.max(0, Date.now() - t);
    const m = Math.round(delta / 60000);
    if (m < 60) return `${m}m ago`;
    const h = Math.round(m / 60);
    if (h < 24) return `${h}h ago`;
    return `${Math.round(h / 24)}d ago`;
}
</script>

<template>
    <ListRow :interactive="false">
        <template #title>{{ signal.host || signal.url }}</template>
        <template #subtitle>
            {{ [signal.country, signal.asn].filter(Boolean).join(' · ') }} · {{ relTime(signal.observedAt) }}
        </template>
        <template #trailing>
            <Chip :tone="tone(signal.result)" dot>{{ signal.result }}</Chip>
        </template>
    </ListRow>
</template>
