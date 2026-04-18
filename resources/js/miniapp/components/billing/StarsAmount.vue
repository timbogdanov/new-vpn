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
        <span class="stars__num tabular-nums">{{ formatted }}</span>
        <span class="stars__glyph" aria-hidden="true">⭐</span>
        <span v-if="usdLabel" class="stars__usd tabular-nums">{{ usdLabel }}</span>
    </span>
</template>

<style scoped>
.stars {
    display: inline-flex;
    align-items: baseline;
    gap: 4px;
    color: var(--color-text-strong);
    font-family: var(--font-mono);
    font-variant-numeric: tabular-nums;
}
.stars__num { font-weight: 500; letter-spacing: -0.01em; }
.stars__glyph { font-family: initial; }
.stars__usd {
    color: var(--color-text-hint);
    font-size: 12px;
    line-height: 16px;
    margin-left: 4px;
    font-weight: 400;
}

.stars--md .stars__num,
.stars--md .stars__glyph { font-size: 15px; line-height: 22px; }
.stars--lg .stars__num,
.stars--lg .stars__glyph { font-size: 24px; line-height: 30px; }
</style>
