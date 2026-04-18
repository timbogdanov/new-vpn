<script setup>
defineProps({
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
});
</script>

<template>
    <header class="ui-page-header">
        <div v-if="$slots.leading" class="ui-page-header__leading">
            <slot name="leading" />
        </div>
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
    align-items: center;
    gap: 14px;
    padding: 8px 4px 16px;
}

.ui-page-header__leading,
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
    font-size: 24px;
    line-height: 30px;
    font-weight: 700;
    letter-spacing: -0.01em;
    color: var(--color-text);
}

.ui-page-header__subtitle {
    margin: 0;
    font-size: 14px;
    line-height: 20px;
    color: var(--color-text-subtle);
    font-variant-numeric: tabular-nums;
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}
</style>
