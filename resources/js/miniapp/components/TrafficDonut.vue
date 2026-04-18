<script setup>
import { computed } from 'vue';

const props = defineProps({
    up: { type: Number, default: 0 },
    down: { type: Number, default: 0 },
    size: { type: Number, default: 104 },
});

const total = computed(() => (props.up || 0) + (props.down || 0));
const downRatio = computed(() => (total.value > 0 ? (props.down / total.value) : 0));
const circumference = computed(() => 2 * Math.PI * 42);
const downStroke = computed(() => circumference.value * downRatio.value);

function format(bytes) {
    if (!bytes) return '0 B';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let i = 0;
    let n = bytes;
    while (n >= 1024 && i < units.length - 1) { n /= 1024; i++; }
    return n.toFixed(n >= 100 || i === 0 ? 0 : 1) + ' ' + units[i];
}
</script>

<template>
    <div class="flex items-center gap-4">
        <svg :width="size" :height="size" viewBox="0 0 100 100" class="shrink-0">
            <circle cx="50" cy="50" r="42" stroke="var(--color-separator)" stroke-width="8" fill="none" />
            <circle
                cx="50" cy="50" r="42"
                stroke="var(--color-text-accent)"
                stroke-width="8"
                fill="none"
                stroke-linecap="round"
                :stroke-dasharray="`${downStroke} ${circumference}`"
                transform="rotate(-90 50 50)"
                style="transition: stroke-dasharray 400ms var(--ease-spring)"
            />
            <text x="50" y="48" text-anchor="middle" fill="currentColor" font-size="14" font-weight="600">
                {{ format(total) }}
            </text>
            <text x="50" y="64" text-anchor="middle" fill="var(--color-text-hint)" font-size="9">
                total
            </text>
        </svg>
        <div class="flex-1 min-w-0 text-sm">
            <div class="flex items-center gap-2 mb-1.5">
                <span class="pill-dot" style="background: var(--color-text-accent)" />
                <span style="color: var(--color-text-hint)">↓</span>
                <span class="tabular-nums">{{ format(down) }}</span>
            </div>
            <div class="flex items-center gap-2">
                <span class="pill-dot" style="background: var(--color-separator)" />
                <span style="color: var(--color-text-hint)">↑</span>
                <span class="tabular-nums">{{ format(up) }}</span>
            </div>
        </div>
    </div>
</template>
