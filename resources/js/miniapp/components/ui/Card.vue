<script setup>
defineProps({
    padding: {
        type: String,
        default: 'md',
        validator: (v) => ['none', 'sm', 'md', 'lg'].includes(v),
    },
    raised: { type: Boolean, default: false },
});
</script>

<template>
    <section
        class="ui-card"
        :class="[`ui-card--p-${padding}`, raised ? 'ui-card--raised' : '']"
    >
        <slot />
    </section>
</template>

<style scoped>
.ui-card {
    background: var(--color-surface);
    border-radius: var(--radius-md);
    border: 1px solid var(--color-separator);
}

.ui-card--raised { background: var(--color-surface-raised); }

.ui-card--p-none { padding: 0; }
.ui-card--p-sm   { padding: 12px; }
.ui-card--p-md   { padding: 20px; }
.ui-card--p-lg   { padding: 24px; }
</style>
