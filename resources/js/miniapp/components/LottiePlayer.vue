<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue';

const props = defineProps({
    src: { type: String, default: '' },
    data: { type: Object, default: null },
    loop: { type: Boolean, default: true },
    autoplay: { type: Boolean, default: true },
    speed: { type: Number, default: 1 },
});

const root = ref(null);
let anim = null;
const reducedMotion = typeof window !== 'undefined'
    && window.matchMedia
    && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

async function load() {
    if (!root.value) return;
    if (reducedMotion) return;
    if (!props.src && !props.data) return;
    const lottie = (await import('lottie-web')).default;
    if (!root.value) return;
    anim?.destroy?.();
    anim = lottie.loadAnimation({
        container: root.value,
        renderer: 'svg',
        loop: props.loop,
        autoplay: props.autoplay,
        path: props.src || undefined,
        animationData: props.data || undefined,
    });
    if (anim?.setSpeed) anim.setSpeed(props.speed);
}

onMounted(load);
watch(() => [props.src, props.data], load);
onBeforeUnmount(() => { anim?.destroy?.(); anim = null; });
</script>

<template>
    <div ref="root" class="lottie-root" aria-hidden="true"></div>
</template>

<style scoped>
.lottie-root { width: 100%; height: 100%; pointer-events: none; }
</style>
