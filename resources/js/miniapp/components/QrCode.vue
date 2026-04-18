<script setup>
import { toRef } from 'vue';
import { useQr } from '../composables/useQr.js';

const props = defineProps({
    value: { type: String, default: '' },
    size: { type: Number, default: 200 },
});

const { dataUrl } = useQr(toRef(props, 'value'), { dark: '#F5F6F7', light: '#00000000' });
</script>

<template>
    <div class="qr">
        <img v-if="dataUrl" :src="dataUrl" :alt="value" :width="size" :height="size" class="qr__img" />
        <div v-else class="skeleton qr__skel" :style="{ width: size + 'px', height: size + 'px' }" />
    </div>
</template>

<style scoped>
.qr {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 16px;
    background: var(--color-surface-raised);
    border-radius: var(--radius-md);
    box-shadow: 0 0 0 1px var(--color-separator) inset;
}
.qr__img { display: block; }
.qr__skel { border-radius: var(--radius-sm); }

[data-theme="light"] .qr__img { filter: invert(1); }
</style>
