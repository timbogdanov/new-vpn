<script setup>
import { computed } from 'vue';
import { t } from '../../i18n.js';

const props = defineProps({
    stars: { type: Number, required: true },
    usdEstimate: { type: Number, default: null },
    size: { type: String, default: 'md' }, // 'md' | 'lg'
});

const sizeClass = computed(() => `stars--${props.size}`);
const formatted = computed(() => new Intl.NumberFormat().format(props.stars));
const usdLabel = computed(() => {
    if (!props.usdEstimate) return '';
    return t('billing.starsApprox', { usd: props.usdEstimate.toFixed(2) });
});
</script>

<template>
    <span class="stars" :class="sizeClass">
        <span class="stars__glyph" aria-hidden="true">★</span>
        <span class="stars__num tabular-nums">{{ formatted }}</span>
        <span v-if="usdLabel" class="stars__usd tabular-nums">{{ usdLabel }}</span>
    </span>
</template>

<style scoped>
.stars { display: inline-flex; align-items: baseline; gap: 6px; color: var(--color-text); }
.stars__glyph { color: var(--color-warning); }
.stars__num { font-weight: 700; letter-spacing: -0.01em; }
.stars__usd { color: var(--color-text-hint); font-size: var(--text-caption); margin-left: 2px; }

.stars--md .stars__glyph,
.stars--md .stars__num { font-size: var(--text-body); line-height: var(--text-body--line-height); }
.stars--lg .stars__glyph,
.stars--lg .stars__num { font-size: var(--text-display); line-height: var(--text-display--line-height); }
</style>
