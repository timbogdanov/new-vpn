<script setup>
defineProps({
    height: { type: Number, default: 80 },
    rounded: { type: String, default: '16px' },
});
</script>

<template>
    <div class="skeleton w-full" :style="{ height: height + 'px', borderRadius: rounded }" />
</template>
