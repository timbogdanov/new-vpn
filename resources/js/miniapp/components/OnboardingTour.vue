<script setup>
import { computed, ref, onMounted } from 'vue';
import Sheet from './ui/Sheet.vue';
import { Button } from './ui/index.js';
import { t } from '../i18n.js';
import { store, markOnboarded } from '../store.js';
import { hap } from '../telegram.js';

const open = ref(false);
const idx = ref(0);

const steps = computed(() => ([
    { title: t('onboarding.step1Title'), body: t('onboarding.step1Body') },
    { title: t('onboarding.step2Title'), body: t('onboarding.step2Body') },
    { title: t('onboarding.step3Title'), body: t('onboarding.step3Body') },
]));

const isLast = computed(() => idx.value === steps.value.length - 1);
const current = computed(() => steps.value[idx.value]);

function shouldShow() {
    if (store.user?.onboardedAt) return false;
    try {
        if (localStorage.getItem('onboardingSeenAt')) return false;
    } catch (_) {}
    return true;
}

function dismiss() {
    open.value = false;
    try { localStorage.setItem('onboardingSeenAt', new Date().toISOString()); } catch (_) {}
    markOnboarded();
}

function next() {
    hap.select();
    if (isLast.value) {
        hap.success();
        dismiss();
    } else {
        idx.value++;
    }
}

onMounted(() => {
    setTimeout(() => { if (shouldShow()) open.value = true; }, 600);
});
</script>

<template>
    <Sheet :open="open" :aria-label="t('onboarding.begin')" @update:open="(v) => !v && dismiss()">
        <div class="onb">
            <div class="onb__top">
                <button v-if="!isLast" type="button" class="onb__skip" @click="dismiss">
                    {{ t('common.skip') }}
                </button>
                <span v-else class="onb__skip-spacer" aria-hidden="true"></span>
            </div>

            <h2 class="onb__title font-display">{{ current.title }}</h2>
            <p class="onb__body">{{ current.body }}</p>

            <div class="onb__dots" aria-hidden="true">
                <span
                    v-for="(s, i) in steps"
                    :key="i"
                    class="onb__dot"
                    :class="{ 'onb__dot--active': i === idx }"
                ></span>
            </div>

            <div class="onb__actions">
                <Button block @click="next">
                    {{ isLast ? t('onboarding.begin') : t('common.next') }}
                </Button>
            </div>
        </div>
    </Sheet>
</template>

<style scoped>
.onb {
    display: flex;
    flex-direction: column;
    gap: 20px;
    padding: 8px 4px 0;
}

.onb__top {
    display: flex;
    justify-content: flex-end;
    min-height: 24px;
}
.onb__skip {
    font-size: 13px;
    line-height: 18px;
    font-weight: 500;
    color: var(--color-text-subtle);
    padding: 4px 8px;
}
.onb__skip:active { color: var(--color-text-strong); }
.onb__skip-spacer { height: 24px; }

.onb__title {
    margin: 0;
    font-size: 28px;
    line-height: 34px;
    color: var(--color-text-strong);
    padding: 0 4px;
}

.onb__body {
    margin: 0;
    padding: 0 4px;
    color: var(--color-text-subtle);
    font-size: 15px;
    line-height: 22px;
    max-width: 34ch;
}

.onb__dots {
    display: flex;
    gap: 6px;
    padding: 0 4px;
}
.onb__dot {
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: var(--color-separator);
    transition: background var(--duration-base) var(--ease-standard);
}
.onb__dot--active { background: var(--color-accent); }

.onb__actions {
    padding-top: 4px;
}

@media (prefers-reduced-motion: reduce) {
    .onb__dot { transition: none; }
}
</style>
