<script setup>
import { computed, ref, watch, onMounted } from 'vue';

const props = defineProps({
    up: { type: Number, default: 0 },
    down: { type: Number, default: 0 },
    size: { type: Number, default: 120 },
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
    <div class="donut">
        <svg :width="size" :height="size" viewBox="0 0 100 100" class="donut__svg">
            <defs>
                <linearGradient id="donut-grad" x1="0%" y1="0%" x2="100%" y2="100%">
                    <stop offset="0%" stop-color="var(--color-accent)" />
                    <stop offset="100%" stop-color="var(--color-accent-hot)" />
                </linearGradient>
            </defs>
            <circle cx="50" cy="50" r="42" stroke="var(--color-gray-4)" stroke-width="2" fill="none" />
            <circle
                cx="50" cy="50" r="42"
                stroke="url(#donut-grad)"
                stroke-width="3"
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
    <div class="donut-legend">
        <div class="donut-legend__row">
            <span class="donut-legend__dot donut-legend__dot--down" aria-hidden="true"></span>
            <span class="donut-legend__label">Download</span>
            <span class="donut-legend__value tabular-nums">{{ format(down) }}</span>
        </div>
        <div class="donut-legend__row">
            <span class="donut-legend__dot donut-legend__dot--up" aria-hidden="true"></span>
            <span class="donut-legend__label">Upload</span>
            <span class="donut-legend__value tabular-nums">{{ format(up) }}</span>
        </div>
    </div>
</template>

<style scoped>
.donut {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.donut__svg { display: block; }
.donut__arc {
    transition: stroke-dasharray var(--duration-slow) var(--ease-spring);
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
    font-size: 18px;
    line-height: 22px;
    font-weight: 700;
    letter-spacing: -0.01em;
    color: var(--color-text);
}
.donut__label {
    font-size: 10px;
    line-height: 12px;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: var(--color-text-hint);
}

.donut-legend {
    margin-top: 16px;
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.donut-legend__row {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 13px;
    line-height: 18px;
    color: var(--color-text-subtle);
}
.donut-legend__dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}
.donut-legend__dot--down { background: var(--color-accent); }
.donut-legend__dot--up   { background: var(--color-gray-4); }
.donut-legend__label { flex: 1 1 auto; color: var(--color-text-hint); }
.donut-legend__value { color: var(--color-text); font-weight: 500; }

@media (prefers-reduced-motion: reduce) {
    .donut__arc { transition: none; }
}
</style>
