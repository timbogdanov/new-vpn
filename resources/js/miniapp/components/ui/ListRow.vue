<script setup>
const props = defineProps({
    title: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    interactive: { type: Boolean, default: true },
    chevron: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    destructive: { type: Boolean, default: false },
    as: { type: String, default: null },
});
defineEmits(['click']);
</script>

<template>
    <component
        :is="props.as ?? (interactive ? 'button' : 'div')"
        class="ui-row"
        :class="{
            'ui-row--interactive': interactive,
            'ui-row--disabled': disabled,
            'ui-row--destructive': destructive,
        }"
        :type="interactive && !props.as ? 'button' : undefined"
        :disabled="interactive && disabled ? true : undefined"
        @click="interactive && !disabled ? $emit('click', $event) : null"
    >
        <span class="ui-row__body">
            <span v-if="title || $slots.title" class="ui-row__title">
                <slot name="title">{{ title }}</slot>
            </span>
            <span v-if="subtitle || $slots.subtitle" class="ui-row__subtitle">
                <slot name="subtitle">{{ subtitle }}</slot>
            </span>
        </span>

        <span v-if="$slots.trailing || chevron" class="ui-row__trailing">
            <slot name="trailing" />
            <svg v-if="chevron" class="ui-row__chevron" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="9 18 15 12 9 6" /></svg>
        </span>
    </component>
</template>

<style scoped>
.ui-row {
    display: flex;
    align-items: center;
    gap: 12px;
    width: 100%;
    min-height: 56px;
    padding: 14px 16px;
    background: transparent;
    color: var(--color-text-strong);
    text-align: left;
    font: inherit;
    border: 0;
    border-radius: 0;
}

.ui-row--interactive {
    cursor: pointer;
    transition: background var(--duration-fast) var(--ease-standard);
}
.ui-row--interactive:active:not(.ui-row--disabled) {
    background: var(--color-surface-raised);
}
.ui-row--disabled { opacity: 0.45; cursor: not-allowed; }
.ui-row--destructive .ui-row__title { color: var(--color-danger); }

.ui-row__body {
    flex: 1 1 auto;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.ui-row__title {
    font-size: 15px;
    line-height: 20px;
    font-weight: 500;
    color: var(--color-text-strong);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.ui-row__subtitle {
    font-size: 13px;
    line-height: 18px;
    color: var(--color-text-subtle);
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.ui-row__trailing {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: var(--color-text-subtle);
    font-variant-numeric: tabular-nums;
    font-size: 13px;
    line-height: 18px;
}

.ui-row__chevron { color: var(--color-text-hint); }

@media (prefers-reduced-motion: reduce) {
    .ui-row--interactive { transition: none; }
}
</style>
