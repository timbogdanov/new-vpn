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
    padding: 2px;
    background: var(--color-surface-raised);
    border-radius: var(--radius-pill);
}

.ui-seg__indicator {
    position: absolute;
    top: 2px;
    bottom: 2px;
    left: 2px;
    width: calc((100% - 4px) / var(--seg-count));
    transform: translate3d(calc(var(--seg-index) * 100%), 0, 0);
    background: var(--color-surface);
    border-radius: var(--radius-pill);
    transition: transform var(--duration-base) var(--ease-standard), opacity var(--duration-fast) var(--ease-standard);
    will-change: transform;
    z-index: 0;
}

.ui-seg__btn {
    position: relative;
    z-index: 1;
    padding: 6px 10px;
    min-height: 32px;
    font-size: 13px;
    line-height: 18px;
    font-weight: 500;
    color: var(--color-text-subtle);
    border-radius: var(--radius-pill);
    transition: color var(--duration-base) var(--ease-standard);
}

.ui-seg__btn.is-active { color: var(--color-text-strong); }

[dir="rtl"] .ui-seg__indicator {
    transform: translate3d(calc(var(--seg-index) * -100%), 0, 0);
}

@media (prefers-reduced-motion: reduce) {
    .ui-seg__indicator,
    .ui-seg__btn { transition: none; }
}
</style>
