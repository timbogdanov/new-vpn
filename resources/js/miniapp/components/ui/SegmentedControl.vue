<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    modelValue: { type: [String, Number], required: true },
    options: {
        type: Array,
        required: true,
        validator: (arr) => arr.every((o) => o && typeof o === 'object' && 'value' in o && 'label' in o),
    },
    ariaLabel: { type: String, default: 'Select option' },
});

const emit = defineEmits(['update:modelValue']);

const buttonRefs = ref([]);

const activeIndex = computed(() => props.options.findIndex((o) => o.value === props.modelValue));

function select(value) {
    if (value !== props.modelValue) emit('update:modelValue', value);
}

function onKeydown(e, i) {
    const last = props.options.length - 1;
    let next = null;
    if (e.key === 'ArrowRight') next = i === last ? 0 : i + 1;
    else if (e.key === 'ArrowLeft') next = i === 0 ? last : i - 1;
    else if (e.key === 'Home') next = 0;
    else if (e.key === 'End') next = last;
    if (next !== null) {
        e.preventDefault();
        select(props.options[next].value);
        buttonRefs.value[next]?.focus();
    }
}
</script>

<template>
    <div class="ui-seg" role="tablist" :aria-label="ariaLabel">
        <div
            class="ui-seg__indicator"
            :style="{
                '--seg-index': activeIndex < 0 ? 0 : activeIndex,
                '--seg-count': options.length,
                opacity: activeIndex < 0 ? 0 : 1,
            }"
            aria-hidden="true"
        ></div>
        <button
            v-for="(opt, i) in options"
            :key="opt.value"
            ref="buttonRefs"
            type="button"
            role="tab"
            :aria-selected="opt.value === modelValue"
            :tabindex="opt.value === modelValue ? 0 : -1"
            class="ui-seg__btn"
            :class="{ 'is-active': opt.value === modelValue }"
            @click="select(opt.value)"
            @keydown="onKeydown($event, i)"
        >
            <span>{{ opt.label }}</span>
        </button>
    </div>
</template>

<style scoped>
.ui-seg {
    position: relative;
    display: grid;
    grid-auto-flow: column;
    grid-auto-columns: 1fr;
    padding: 3px;
    background: var(--color-surface-raised);
    border-radius: var(--radius-sm);
    box-shadow: 0 0 0 1px var(--color-separator) inset;
}

.ui-seg__indicator {
    position: absolute;
    top: 3px;
    bottom: 3px;
    left: 3px;
    width: calc((100% - 6px) / var(--seg-count));
    transform: translate3d(calc(var(--seg-index) * 100%), 0, 0);
    background: var(--color-surface);
    border-radius: calc(var(--radius-sm) - 2px);
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.18), 0 0 0 1px var(--color-separator) inset;
    transition: transform var(--duration-base) var(--ease-spring), opacity var(--duration-fast) var(--ease-out);
    will-change: transform;
    z-index: 0;
}

.ui-seg__btn {
    position: relative;
    z-index: 1;
    padding: 6px 10px;
    min-height: 30px;
    font-size: 13px;
    line-height: 18px;
    font-weight: 500;
    color: var(--color-text-subtle);
    border-radius: calc(var(--radius-sm) - 2px);
    transition: color var(--duration-base) var(--ease-out);
}

.ui-seg__btn.is-active { color: var(--color-text); font-weight: 600; }

[dir="rtl"] .ui-seg__indicator {
    transform: translate3d(calc(var(--seg-index) * -100%), 0, 0);
}

@media (prefers-reduced-motion: reduce) {
    .ui-seg__indicator,
    .ui-seg__btn { transition: none; }
}
</style>
