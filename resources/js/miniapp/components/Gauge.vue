<script setup>
import { computed, onMounted, ref, watch } from 'vue';

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

const display = ref(0);
let frame = null;

function animateTo(target, duration = 450) {
    const start = display.value;
    const delta = target - start;
    const t0 = performance.now();
    const ease = (t) => 1 - Math.pow(1 - t, 3);
    function step(t) {
        const p = Math.min(1, (t - t0) / duration);
        display.value = start + delta * ease(p);
        if (p < 1) frame = requestAnimationFrame(step);
    }
    if (frame) cancelAnimationFrame(frame);
    frame = requestAnimationFrame(step);
}

onMounted(() => animateTo(props.value));
watch(() => props.value, (v) => animateTo(v));

const formatted = computed(() => {
    const v = display.value;
    if (v >= 100) return v.toFixed(0);
    if (v >= 10)  return v.toFixed(1);
    return v.toFixed(2);
});
</script>

<template>
    <div class="gauge" :style="{ width: size + 'px' }">
        <svg :width="size" :height="size" viewBox="0 0 100 100" aria-hidden="true" class="gauge__svg">
            <circle cx="50" cy="50" :r="radius" stroke="var(--color-separator)" stroke-width="6" fill="none" />
            <circle
                cx="50" cy="50" :r="radius"
                stroke="var(--color-text-strong)"
                stroke-width="6"
                fill="none"
                stroke-linecap="round"
                :stroke-dasharray="`${arc} ${circumference}`"
                transform="rotate(-90 50 50)"
                class="gauge__arc"
            />
        </svg>
        <div class="gauge__center" :style="{ height: size + 'px' }">
            <div class="gauge__value tabular-nums">{{ formatted }}</div>
            <div v-if="unit" class="gauge__unit">{{ unit }}</div>
        </div>
        <div v-if="label" class="gauge__label">{{ label }}</div>
    </div>
</template>

<style scoped>
.gauge {
    position: relative;
    display: inline-flex;
    flex-direction: column;
    align-items: center;
}
.gauge__svg { display: block; }
.gauge__arc { transition: stroke-dasharray var(--duration-slow) var(--ease-standard); }
.gauge__center {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 2px;
    pointer-events: none;
}
.gauge__value {
    font-family: var(--font-mono);
    font-size: 24px;
    line-height: 28px;
    font-weight: 500;
    color: var(--color-text-strong);
}
.gauge__unit {
    font-size: 11px;
    line-height: 14px;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--color-text-hint);
    font-weight: 600;
}
.gauge__label {
    margin-top: 8px;
    font-size: 11px;
    line-height: 14px;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--color-text-hint);
    font-weight: 600;
}

@media (prefers-reduced-motion: reduce) {
    .gauge__arc { transition: none; }
}
</style>
