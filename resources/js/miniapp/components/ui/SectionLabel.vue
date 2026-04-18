<template>
    <div class="ui-section-label">
        <slot />
    </div>
</template>

<style scoped>
.ui-section-label {
    font-size: 11px;
    line-height: 16px;
    font-weight: 700;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: color-mix(in srgb, var(--color-text-hint) 80%, var(--color-text-subtle));
    padding: 0 4px 10px;
}
</style>
