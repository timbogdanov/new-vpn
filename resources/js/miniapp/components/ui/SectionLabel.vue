<template>
    <div class="ui-section-label">
        <slot />
    </div>
</template>

<style scoped>
.ui-section-label {
    font-size: 11px;
    line-height: 16px;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: var(--color-text-hint);
    padding: 0 4px 8px;
}
</style>
