<template>
    <div class="ui-list-group">
        <slot />
    </div>
</template>

<style scoped>
.ui-list-group {
    background: var(--color-surface);
    border-radius: var(--radius-lg);
    overflow: hidden;
}

.ui-list-group :deep(.ui-row + .ui-row) {
    border-top: 1px solid var(--color-separator);
}
</style>
