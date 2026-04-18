<script setup>
import { computed } from 'vue';
import { Globe2 } from 'lucide-vue-next';
import BentoTile from './BentoTile.vue';
import { store } from '../../store.js';
import { t } from '../../i18n.js';

defineEmits(['click']);
const count = computed(() => store.availableServers.length);
</script>

<template>
    <BentoTile span="1" surface="surface" interactive @click="$emit('click')">
        <div class="srv-tile">
            <Globe2 :size="20" :stroke-width="1.75" class="srv-tile__icon" />
            <p class="srv-tile__h">{{ t('home.allServers') }}</p>
            <p class="srv-tile__sub tabular-nums">{{ t('home.gateways', { n: count }) }}</p>
        </div>
    </BentoTile>
</template>

<style scoped>
.srv-tile { display: flex; flex-direction: column; gap: 6px; height: 100%; }
.srv-tile__icon { color: var(--color-accent); }
.srv-tile__h {
    font-family: var(--font-display);
    font-size: var(--text-title);
    line-height: var(--text-title--line-height);
    font-weight: 600;
    margin: auto 0 0;
}
.srv-tile__sub { color: var(--color-text-hint); font-size: var(--text-label); margin: 0; }
</style>
