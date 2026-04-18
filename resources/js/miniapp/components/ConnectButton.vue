<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { MainButton, hap, openDeepLink, isRealTelegram } from '../telegram.js';
import { t } from '../i18n.js';
import { Button } from './ui/index.js';

const props = defineProps({
    // The fully-built deep-link URL (https redirect wrapper, not raw custom scheme).
    // When empty, the button is inert until parent provisioning completes.
    url: { type: String, default: '' },
    label: { type: String, default: '' },
    // Optional async provisioner called if a click fires while url is still empty.
    // Must resolve to the now-built URL. On iOS this path still runs through async
    // so the deep link may be blocked — prefer having url ready before enabling.
    provision: { type: Function, default: null },
});

const busy = ref(false);
const enabled = computed(() => !!props.url && !busy.value);

let cleanup = null;

function openSync(url) {
    if (!url) return;
    openDeepLink(url);
    hap.success();
}

// Synchronous click path — critical on iOS so the user-gesture context isn't
// broken by an intervening await. When a URL is already provisioned we open
// immediately. Only fall back to the async provisioner when we absolutely must.
function fire() {
    if (busy.value) return;

    if (props.url) {
        hap.medium();
        openSync(props.url);
        return;
    }

    if (!props.provision) return;

    busy.value = true;
    hap.medium();
    MainButton.setProgress(true);
    Promise.resolve(props.provision())
        .then((url) => openSync(url))
        .catch(() => hap.error())
        .finally(() => {
            busy.value = false;
            MainButton.setProgress(false);
        });
}

function bind() {
    if (cleanup) { cleanup(); cleanup = null; }
    cleanup = MainButton.show(props.label || t('server.connect'), fire);
}

onMounted(bind);
watch(() => [props.label, props.url], bind);

onBeforeUnmount(() => {
    if (cleanup) { cleanup(); cleanup = null; }
});
</script>

<template>
    <!-- Fallback inline button for environments without Telegram's MainButton
         (desktop browser preview, standalone dev shim). Inside real Telegram
         the bottom MainButton is the sole connect affordance. -->
    <Button
        v-if="!isRealTelegram"
        variant="primary"
        block
        :loading="busy"
        :disabled="!enabled && !provision"
        @click="fire"
    >
        {{ busy ? t('server.connecting') : (label || t('server.connect')) }}
    </Button>
</template>
