<script setup>
import { computed } from 'vue';

const props = defineProps({
    points: { type: Array, required: true }, // [{date, measurements, anomaly, confirmed}]
    height: { type: Number, default: 72 },
    ariaLabel: { type: String, default: 'Censorship timeseries' },
});

const width = 320;
const padY = 6;

const prepared = computed(() => {
    const pts = props.points || [];
    if (!pts.length) return { total: [], bad: [], max: 0, empty: true };
    const max = pts.reduce((m, p) => Math.max(m, p.measurements || 0), 0);
    const stride = pts.length > 1 ? width / (pts.length - 1) : width;
    const scaleY = (v) => {
        if (!max) return props.height - padY;
        return props.height - padY - (v / max) * (props.height - padY * 2);
    };
    const totalPath = pts.map((p, i) => `${i === 0 ? 'M' : 'L'} ${i * stride},${scaleY(p.measurements || 0)}`).join(' ');
    const badArea = [
        ...pts.map((p, i) => `${i === 0 ? 'M' : 'L'} ${i * stride},${scaleY((p.anomaly || 0) + (p.confirmed || 0))}`),
        `L ${(pts.length - 1) * stride},${props.height - padY}`,
        `L 0,${props.height - padY}`,
        'Z',
    ].join(' ');
    return { total: totalPath, bad: badArea, max, empty: max === 0 };
});
</script>

<template>
    <div class="sparkline" :aria-label="ariaLabel" role="img">
        <svg :viewBox="`0 0 ${width} ${height}`" :width="width" :height="height" preserveAspectRatio="none">
            <path class="sparkline__bad" :d="prepared.bad" />
            <path class="sparkline__total" :d="prepared.total" />
        </svg>
        <p v-if="prepared.empty" class="sparkline__empty">{{ $slots.empty ? '' : '—' }}</p>
    </div>
</template>

<style scoped>
.sparkline {
    position: relative;
    width: 100%;
    padding: 4px 2px;
}
.sparkline svg {
    width: 100%;
    height: auto;
    display: block;
}
.sparkline__total {
    fill: none;
    stroke: var(--color-text-strong);
    stroke-width: 1.25;
    stroke-linejoin: round;
    vector-effect: non-scaling-stroke;
}
.sparkline__bad {
    fill: color-mix(in srgb, var(--color-danger) 24%, transparent);
    stroke: var(--color-danger);
    stroke-width: 1;
    vector-effect: non-scaling-stroke;
}
.sparkline__empty {
    position: absolute;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0;
    color: var(--color-text-hint);
    font-size: 12px;
}
</style>
