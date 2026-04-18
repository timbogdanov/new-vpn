<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import PaywallSheet from '../components/billing/PaywallSheet.vue';

const router = useRouter();
const open = ref(true);

function close(v) {
    open.value = v;
    if (!v) router.back();
}
</script>

<template>
    <PaywallSheet v-model:open="open" @update:open="close" />
</template>
