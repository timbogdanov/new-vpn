<script setup>
import { computed } from 'vue';
import { ArrowDownUp } from 'lucide-vue-next';
import BentoTile from './BentoTile.vue';
import { store } from '../../store.js';
import { t } from '../../i18n.js';

defineEmits(['click']);

const total = computed(() => {
    const tt = store.profile?.totalTraffic;
    return (tt?.up || 0) + (tt?.down || 0);
});

function fmtBytes(b) {
    if (!b) return '0 MB';
    const units = ['B', 'KB', 'MB', 'GB', 'TB'];
    let i = 0; let n = b;
    while (n >= 1024 && i < units.length - 1) { n /= 1024; i++; }
    return n.toFixed(n >= 100 || i === 0 ? 0 : 1) + ' ' + units[i];
}
</script>

<template>
    <BentoTile span="1" surface="surface" interactive @click="$emit('click')">
        <div class="stats-tile">
            <header class="stats-tile__head">
                <span class="stats-tile__eyebrow">{{ t('profile.trafficTotal') }}</span>
                <ArrowDownUp :size="16" :stroke-width="1.75" />
            </header>
            <p class="stats-tile__h tabular-nums">{{ fmtBytes(total) }}</p>
            <p class="stats-tile__sub">{{ t('profile.title') }} →</p>
        </div>
    </BentoTile>
</template>

<style scoped>
.stats-tile { display: flex; flex-direction: column; gap: 6px; height: 100%; }
.stats-tile__head { display: flex; align-items: center; justify-content: space-between; color: var(--color-text-hint); }
.stats-tile__eyebrow { font-size: var(--text-caption); text-transform: uppercase; letter-spacing: 0.06em; font-weight: 600; }
.stats-tile__h {
    font-family: var(--font-display);
    font-size: var(--text-title);
    line-height: var(--text-title--line-height);
    font-weight: 600;
    margin: auto 0 0;
}
.stats-tile__sub { color: var(--color-accent); font-size: var(--text-label); margin: 0; font-weight: 600; }
</style>
