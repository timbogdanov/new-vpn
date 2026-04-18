<script setup>
import { computed, ref, watch, onMounted } from 'vue';

const props = defineProps({
    up: { type: Number, default: 0 },
    down: { type: Number, default: 0 },
    size: { type: Number, default: 128 },
});

const total = computed(() => (props.up || 0) + (props.down || 0));
const downRatio = computed(() => (total.value > 0 ? props.down / total.value : 0));
const circumference = computed(() => 2 * Math.PI * 42);
const downStroke = computed(() => circumference.value * downRatio.value);

const displayTotal = ref(0);
let animFrame = null;

function animateTo(target, duration = 450) {
    const start = displayTotal.value;
    const delta = target - start;
    const t0 = performance.now();
    const ease = (t) => 1 - Math.pow(1 - t, 3);

    function step(t) {
        const p = Math.min(1, (t - t0) / duration);
        displayTotal.value = start + delta * ease(p);
        if (p < 1) animFrame = requestAnimationFrame(step);
    }
    if (animFrame) cancelAnimationFrame(animFrame);
    animFrame = requestAnimationFrame(step);
}

function format(bytes) {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let i = 0;
    let n = bytes;
    while (n >= 1024 && i < units.length - 1) { n /= 1024; i++; }
    return n.toFixed(n >= 100 || i === 0 ? 0 : 1) + ' ' + units[i];
}

onMounted(() => animateTo(total.value));
watch(() => total.value, (v) => animateTo(v));
</script>

<template>
    <div class="donut-wrap">
        <div class="donut" :style="{ width: size + 'px', height: size + 'px' }">
            <svg :width="size" :height="size" viewBox="0 0 100 100" class="donut__svg">
                <circle cx="50" cy="50" r="42" stroke="var(--color-separator)" stroke-width="8" fill="none" />
                <circle
                    cx="50" cy="50" r="42"
                    stroke="var(--color-accent)"
                    stroke-width="8"
                    fill="none"
                    stroke-linecap="round"
                    :stroke-dasharray="`${downStroke} ${circumference}`"
                    transform="rotate(-90 50 50)"
                    class="donut__arc"
                />
            </svg>
            <div class="donut__center">
                <div class="donut__value tabular-nums">{{ format(displayTotal) }}</div>
                <div class="donut__label">total</div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.donut-wrap {
    display: flex;
    justify-content: center;
    padding: 8px 0;
}
.donut {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.donut__svg { display: block; }
.donut__arc {
    transition: stroke-dasharray var(--duration-slow) var(--ease-standard);
}
.donut__center {
    position: absolute;
    inset: 0;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 2px;
}
.donut__value {
    font-family: var(--font-mono);
    font-size: 17px;
    line-height: 22px;
    font-weight: 500;
    color: var(--color-text-strong);
}
.donut__label {
    font-size: 11px;
    line-height: 14px;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: var(--color-text-hint);
    font-weight: 600;
}

@media (prefers-reduced-motion: reduce) {
    .donut__arc { transition: none; }
}
</style>
