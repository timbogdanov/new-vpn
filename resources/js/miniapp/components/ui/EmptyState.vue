<script setup>
defineProps({
    title: { type: String, default: '' },
    body: { type: String, default: '' },
});
</script>

<template>
    <div class="ui-empty">
        <div v-if="$slots.icon" class="ui-empty__icon">
            <slot name="icon" />
        </div>
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
    gap: 8px;
    padding: 32px 20px;
}

.ui-empty__icon {
    width: 44px;
    height: 44px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: var(--color-text-hint);
    background: var(--color-surface-raised);
    border-radius: var(--radius-sm);
    margin-bottom: 4px;
}

.ui-empty__title {
    margin: 0;
    font-size: 16px;
    line-height: 22px;
    font-weight: 600;
    color: var(--color-text);
}

.ui-empty__body {
    margin: 0;
    font-size: 14px;
    line-height: 20px;
    color: var(--color-text-hint);
    max-width: 28ch;
}

.ui-empty__action {
    margin-top: 8px;
}
</style>
