<script setup>
import { computed } from 'vue';

const props = defineProps({
    value: { type: Number, default: 0 },
    max: { type: Number, default: 200 },
    label: { type: String, default: '' },
    unit: { type: String, default: '' },
    size: { type: Number, default: 120 },
});

const pct = computed(() => Math.min(1, Math.max(0, props.value / props.max)));
const radius = 42;
const circumference = 2 * Math.PI * radius;
const arc = computed(() => circumference * pct.value);
</script>

<template>
    <div class="flex flex-col items-center">
        <svg :width="size" :height="size" viewBox="0 0 100 100">
            <circle cx="50" cy="50" :r="radius" stroke="var(--color-separator)" stroke-width="8" fill="none" />
            <circle
                cx="50" cy="50" :r="radius"
                stroke="var(--color-text-accent)"
                stroke-width="8"
                fill="none"
                stroke-linecap="round"
                :stroke-dasharray="`${arc} ${circumference}`"
                transform="rotate(-90 50 50)"
                style="transition: stroke-dasharray 500ms var(--ease-spring)"
            />
            <text x="50" y="50" text-anchor="middle" fill="currentColor" font-size="18" font-weight="700">
                {{ value.toFixed(value > 10 ? 0 : 1) }}
            </text>
            <text x="50" y="64" text-anchor="middle" fill="var(--color-text-hint)" font-size="9">
                {{ unit }}
            </text>
        </svg>
        <div v-if="label" class="mt-1 text-xs" style="color: var(--color-text-hint)">{{ label }}</div>
    </div>
</template>
