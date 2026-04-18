<script setup>
import { computed } from 'vue';

/**
 * Flat SVG flag icons for countries we operate servers in.
 * Uses a stylised 3:2 rect clipped to a rounded square, with a 1px inner hairline.
 * Falls back to a 2-letter monogram chip for countries outside the bundle.
 */

const props = defineProps({
    code: { type: String, default: '' },
    emoji: { type: String, default: '' }, // kept for back-compat; ignored
    size: { type: Number, default: 40 },
    rounded: { type: String, default: 'md' }, // md | pill
});

const normalizedCode = computed(() => (props.code || '').toUpperCase().slice(0, 2));

// Each flag is a minimal set of SVG paths/rects rendered inside a 60x40 viewBox.
// We clip to a rounded rect and add a subtle hairline on top.
const FLAGS = {
    US: () => `
        <defs>
            <pattern id="us-stripes" width="60" height="${40 / 13}" patternUnits="userSpaceOnUse">
                <rect width="60" height="${40 / 13}" fill="#B22234"/>
                <rect y="${40 / 26}" width="60" height="${40 / 26}" fill="#fff"/>
            </pattern>
        </defs>
        <rect width="60" height="40" fill="url(#us-stripes)"/>
        <rect width="26" height="21" fill="#3C3B6E"/>
    `,
    DE: () => `
        <rect width="60" height="13.33" y="0"     fill="#000"/>
        <rect width="60" height="13.33" y="13.33" fill="#DD0000"/>
        <rect width="60" height="13.34" y="26.66" fill="#FFCE00"/>
    `,
    NL: () => `
        <rect width="60" height="13.33" y="0"     fill="#AE1C28"/>
        <rect width="60" height="13.33" y="13.33" fill="#fff"/>
        <rect width="60" height="13.34" y="26.66" fill="#21468B"/>
    `,
    SG: () => `
        <rect width="60" height="20" y="0"  fill="#EF3340"/>
        <rect width="60" height="20" y="20" fill="#fff"/>
        <circle cx="14.5" cy="14.5" r="6" fill="#fff"/>
        <circle cx="17.5" cy="14.5" r="6" fill="#EF3340"/>
    `,
    FI: () => `
        <rect width="60" height="40" fill="#fff"/>
        <rect x="20" y="0" width="7" height="40" fill="#003580"/>
        <rect x="0" y="16.5" width="60" height="7" fill="#003580"/>
    `,
    FR: () => `
        <rect width="20" height="40" x="0"  fill="#002395"/>
        <rect width="20" height="40" x="20" fill="#fff"/>
        <rect width="20" height="40" x="40" fill="#ED2939"/>
    `,
    GB: () => `
        <rect width="60" height="40" fill="#012169"/>
        <path d="M0 0 L60 40 M60 0 L0 40" stroke="#fff" stroke-width="8"/>
        <path d="M0 0 L60 40 M60 0 L0 40" stroke="#C8102E" stroke-width="4"/>
        <path d="M30 0 V40 M0 20 H60" stroke="#fff" stroke-width="12"/>
        <path d="M30 0 V40 M0 20 H60" stroke="#C8102E" stroke-width="6"/>
    `,
};

const hasFlag = computed(() => !!FLAGS[normalizedCode.value]);
const flagInner = computed(() => (hasFlag.value ? FLAGS[normalizedCode.value]() : ''));

const radiusStyle = computed(() => {
    if (props.rounded === 'pill') return `${props.size / 2}px`;
    return '6px';
});

const dims = computed(() => ({
    width: `${props.size}px`,
    height: `${Math.round((props.size * 2) / 3)}px`,
    borderRadius: radiusStyle.value,
}));
</script>

<template>
    <span class="flag-icon" :style="dims" :aria-label="normalizedCode || 'flag'" role="img">
        <svg
            v-if="hasFlag"
            viewBox="0 0 60 40"
            preserveAspectRatio="xMidYMid slice"
            class="flag-icon__svg"
            aria-hidden="true"
            v-html="flagInner"
        />
        <span v-else class="flag-icon__mono" aria-hidden="true">{{ normalizedCode || '??' }}</span>
        <span class="flag-icon__border" aria-hidden="true"></span>
    </span>
</template>

<style scoped>
.flag-icon {
    position: relative;
    display: inline-block;
    overflow: hidden;
    flex-shrink: 0;
    background: var(--color-surface-raised);
}
.flag-icon__svg {
    display: block;
    width: 100%;
    height: 100%;
}
.flag-icon__mono {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 100%;
    height: 100%;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.04em;
    color: var(--color-text-subtle);
}
.flag-icon__border {
    position: absolute;
    inset: 0;
    border-radius: inherit;
    box-shadow: 0 0 0 1px rgba(0, 0, 0, 0.15) inset;
    pointer-events: none;
}
</style>
