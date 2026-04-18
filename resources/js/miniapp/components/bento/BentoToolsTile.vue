<script setup>
import { Shield, Gauge } from 'lucide-vue-next';
import BentoTile from './BentoTile.vue';
import { t } from '../../i18n.js';

const emit = defineEmits(['ip', 'speed']);
</script>

<template>
    <BentoTile span="2" surface="surface">
        <div class="tools-tile">
            <button class="tools-tile__half" @click="emit('ip')">
                <Shield :size="20" :stroke-width="1.75" class="tools-tile__icon" />
                <span class="tools-tile__label">{{ t('home.toolsIp') }}</span>
            </button>
            <span class="tools-tile__sep" aria-hidden="true"></span>
            <button class="tools-tile__half" @click="emit('speed')">
                <Gauge :size="20" :stroke-width="1.75" class="tools-tile__icon" />
                <span class="tools-tile__label">{{ t('home.toolsSpeed') }}</span>
            </button>
        </div>
    </BentoTile>
</template>

<style scoped>
.tools-tile { display: flex; align-items: stretch; height: 100%; gap: 0; }
.tools-tile__half {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    justify-content: space-between;
    gap: 14px;
    background: transparent;
    border: 0;
    color: inherit;
    cursor: pointer;
    padding: 4px;
    text-align: left;
    transition: opacity var(--duration-fast) var(--ease-out);
}
.tools-tile__half:active { opacity: 0.7; }
.tools-tile__sep { width: 1px; background: var(--color-separator); }
.tools-tile__icon { color: var(--color-accent); }
.tools-tile__label { font-weight: 600; font-size: var(--text-body); }
</style>
