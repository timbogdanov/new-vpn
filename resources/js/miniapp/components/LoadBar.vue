<script setup>
import { computed } from 'vue';

const props = defineProps({
    percent: { type: Number, default: null },
});

const pct = computed(() => (props.percent == null ? 0 : Math.min(100, Math.max(0, props.percent))));
const color = computed(() => {
    if (pct.value < 40) return 'var(--color-success)';
    if (pct.value < 75) return 'var(--color-warning)';
    return 'var(--color-destructive)';
});
</script>

<template>
    <div class="flex items-center gap-2">
        <div class="flex-1 h-1.5 rounded-full overflow-hidden" style="background: rgba(255,255,255,0.07)">
            <div class="h-full rounded-full transition-all duration-500"
                 :style="{ width: pct + '%', background: color }" />
        </div>
        <span class="text-[11px] tabular-nums" style="color: var(--color-text-hint)">
            <template v-if="percent != null">{{ pct }}%</template>
            <template v-else>—</template>
        </span>
    </div>
</template>
