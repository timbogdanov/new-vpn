<script setup>
import { toRef } from 'vue';
import { useQr } from '../composables/useQr.js';

const props = defineProps({
    value: { type: String, default: '' },
    size: { type: Number, default: 180 },
});

const { dataUrl } = useQr(toRef(props, 'value'), { dark: '#f5f6f7', light: '#00000000' });
</script>

<template>
    <div
        class="flex items-center justify-center rounded-2xl p-3"
        :style="{ width: (size + 24) + 'px', height: (size + 24) + 'px', background: 'rgba(255,255,255,0.04)' }"
    >
        <img v-if="dataUrl" :src="dataUrl" :alt="value" :width="size" :height="size" />
        <div v-else class="skeleton" :style="{ width: size + 'px', height: size + 'px' }" />
    </div>
</template>
