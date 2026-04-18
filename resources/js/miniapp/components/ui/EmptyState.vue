<script setup>
defineProps({
    title: { type: String, default: '' },
    body: { type: String, default: '' },
});
</script>

<template>
    <div class="ui-empty">
        <h2 v-if="title || $slots.title" class="ui-empty__title">
            <slot name="title">{{ title }}</slot>
        </h2>
        <p v-if="body || $slots.body" class="ui-empty__body">
            <slot name="body">{{ body }}</slot>
        </p>
        <div v-if="$slots.action" class="ui-empty__action">
            <slot name="action" />
        </div>
    </div>
</template>

<style scoped>
.ui-empty {
    display: flex;
    flex-direction: column;
    align-items: center;
    text-align: center;
    gap: 6px;
    padding: 48px 20px;
}

.ui-empty__title {
    margin: 0;
    font-size: 15px;
    line-height: 22px;
    font-weight: 500;
    color: var(--color-text-strong);
}

.ui-empty__body {
    margin: 0;
    font-size: 13px;
    line-height: 18px;
    color: var(--color-text-subtle);
    max-width: 32ch;
}

.ui-empty__action {
    margin-top: 12px;
}
</style>
