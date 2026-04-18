<template>
    <div class="ui-section-label">
        <slot />
    </div>
</template>

<style scoped>
.ui-section-label {
    font-size: 11px;
    line-height: 14px;
    font-weight: 600;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--color-text-hint);
    padding: 0 16px 8px;
}
</style>
