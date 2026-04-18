<script setup>
import { computed } from 'vue';

const props = defineProps({
    ms: { type: Number, default: null },
    label: { type: String, default: '' },
});

const tier = computed(() => {
    if (props.ms == null) return 'unknown';
    if (props.ms < 80) return 'fast';
    if (props.ms < 200) return 'ok';
    return 'slow';
});

const color = computed(() => ({
    fast: 'var(--color-success)',
    ok: 'var(--color-warning)',
    slow: 'var(--color-destructive)',
    unknown: 'var(--color-text-hint)',
}[tier.value]));
</script>

<template>
    <span class="chip" :style="{ color }">
        <span class="pill-dot" :style="{ background: color }" />
        <span v-if="ms != null">{{ ms }} ms</span>
        <span v-else>—</span>
        <span v-if="label" style="color: var(--color-text-hint)">· {{ label }}</span>
    </span>
</template>
