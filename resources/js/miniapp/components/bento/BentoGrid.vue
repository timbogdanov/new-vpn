<script setup>
defineProps({
    gap: { type: String, default: '12px' },
});
</script>

<template>
    <div class="bento-grid" :style="{ gap }">
        <slot />
    </div>
</template>

<style scoped>
.bento-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    grid-auto-rows: minmax(96px, auto);
    grid-auto-flow: dense;
}

@media (max-width: 360px) {
    .bento-grid { grid-template-columns: 1fr; }
}
</style>
