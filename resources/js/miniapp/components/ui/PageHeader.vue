<script setup>
defineProps({
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
});
</script>

<template>
    <header class="ui-page-header">
        <div class="ui-page-header__body">
            <h1 v-if="title || $slots.title" class="ui-page-header__title">
                <slot name="title">{{ title }}</slot>
            </h1>
            <p v-if="subtitle || $slots.subtitle" class="ui-page-header__subtitle">
                <slot name="subtitle">{{ subtitle }}</slot>
            </p>
        </div>
        <div v-if="$slots.trailing" class="ui-page-header__trailing">
            <slot name="trailing" />
        </div>
    </header>
</template>

<style scoped>
.ui-page-header {
    display: flex;
    align-items: flex-end;
    gap: 12px;
    padding: 8px 0 16px;
}

.ui-page-header__trailing {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
}

.ui-page-header__body {
    flex: 1 1 auto;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.ui-page-header__title {
    margin: 0;
    font-family: var(--font-sans);
    font-size: 20px;
    line-height: 26px;
    font-weight: 600;
    letter-spacing: -0.01em;
    color: var(--color-text-strong);
}

.ui-page-header__subtitle {
    margin: 0;
    font-size: 13px;
    line-height: 18px;
    color: var(--color-text-subtle);
}
</style>
