<script setup>
import { ref, watch } from 'vue';
import { t } from '../i18n.js';

const props = defineProps({
    modelValue: { type: String, default: '' },
    loading: { type: Boolean, default: false },
    placeholder: { type: String, default: '' },
});
const emit = defineEmits(['update:modelValue', 'search']);

const inputRef = ref(null);
let debounce = null;

watch(() => props.modelValue, (val) => {
    if (debounce) clearTimeout(debounce);
    debounce = setTimeout(() => {
        emit('search', (val || '').trim());
    }, 220);
});

function onInput(e) {
    emit('update:modelValue', e.target.value);
}

function clear() {
    emit('update:modelValue', '');
    inputRef.value?.focus();
}

defineExpose({ focus: () => inputRef.value?.focus() });
</script>

<template>
    <div class="url-search">
        <svg class="url-search__icon" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <circle cx="11" cy="11" r="7" />
            <line x1="20" y1="20" x2="16.65" y2="16.65" />
        </svg>
        <input
            ref="inputRef"
            :value="modelValue"
            type="search"
            inputmode="url"
            autocomplete="off"
            autocapitalize="off"
            autocorrect="off"
            spellcheck="false"
            :placeholder="placeholder || t('tools.freedomSearchPlaceholder')"
            class="url-search__input"
            role="combobox"
            @input="onInput"
        />
        <button v-if="modelValue" type="button" class="url-search__clear" aria-label="Clear" @click="clear">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        </button>
    </div>
</template>

<style scoped>
.url-search {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    background: var(--color-surface);
    border-radius: var(--radius-lg);
    color: var(--color-text-strong);
}
.url-search__icon { color: var(--color-text-hint); flex-shrink: 0; }
.url-search__input {
    flex: 1 1 auto;
    min-width: 0;
    background: transparent;
    border: 0;
    font: inherit;
    color: inherit;
    outline: none;
}
.url-search__input::placeholder { color: var(--color-text-hint); }
.url-search__clear {
    color: var(--color-text-hint);
    padding: 4px;
    border-radius: var(--radius-pill);
    transition: background var(--duration-fast) var(--ease-standard);
}
.url-search__clear:active { background: var(--color-surface-raised); }
</style>
