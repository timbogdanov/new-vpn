<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { MainButton, hap, openDeepLink } from '../telegram.js';
import { t } from '../i18n.js';
import { Button } from './ui/index.js';

const props = defineProps({
    enabled: { type: Boolean, default: true },
    busy: { type: Boolean, default: false },
    label: { type: String, default: '' },
    onClick: { type: Function, required: true },
});

const busy = ref(false);
let cleanup = null;

async function fire() {
    if (!props.enabled || busy.value) return;
    busy.value = true;
    hap.medium();
    MainButton.setProgress(true);
    try {
        const deep = await props.onClick();
        if (deep) openDeepLink(deep);
        hap.success();
    } catch (_) {
        hap.error();
    } finally {
        busy.value = false;
        MainButton.setProgress(false);
    }
}

function bind() {
    if (cleanup) { cleanup(); cleanup = null; }
    cleanup = MainButton.show(props.label || t('server.connect'), fire);
}

onMounted(bind);
watch(() => [props.label, props.enabled], bind);

onBeforeUnmount(() => {
    if (cleanup) { cleanup(); cleanup = null; }
});
</script>

<template>
    <!-- In-page fallback for when MainButton isn't available (dev / web preview) -->
    <Button variant="primary" block :loading="busy" :disabled="!enabled" @click="fire">
        {{ busy ? t('server.connecting') : (label || t('server.connect')) }}
    </Button>
</template>
