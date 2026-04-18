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
    { title: t('onboarding.step1Title'), body: t('onboarding.step1Body'), emoji: '⚡' },
    { title: t('onboarding.step2Title'), body: t('onboarding.step2Body'), emoji: '🌐' },
    { title: t('onboarding.step3Title'), body: t('onboarding.step3Body'), emoji: '✨' },
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
            <div class="onb__art" aria-hidden="true">
                <span class="onb__emoji">{{ current.emoji }}</span>
            </div>
            <h2 class="onb__title">{{ current.title }}</h2>
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
                <Button variant="ghost" size="sm" @click="dismiss">{{ t('common.skip') }}</Button>
                <Button @click="next">
                    {{ isLast ? t('onboarding.begin') : t('common.next') }}
                </Button>
            </div>
        </div>
    </Sheet>
</template>

<style scoped>
.onb { display: flex; flex-direction: column; gap: 16px; padding: 8px 4px 4px; align-items: center; text-align: center; }
.onb__art {
    width: 96px;
    height: 96px;
    border-radius: var(--radius-xl);
    background: var(--gradient-aurora);
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: var(--shadow-hero);
}
.onb__emoji { font-size: 44px; line-height: 1; }
.onb__title {
    font-family: var(--font-display);
    font-size: var(--text-display);
    line-height: var(--text-display--line-height);
    margin: 0;
    font-weight: 600;
}
.onb__body { color: var(--color-text-subtle); font-size: var(--text-body); line-height: var(--text-body--line-height); margin: 0; max-width: 32ch; }
.onb__dots { display: flex; gap: 6px; margin: 4px 0; }
.onb__dot {
    width: 6px; height: 6px; border-radius: 50%;
    background: var(--color-gray-4);
    transition: background var(--duration-base) var(--ease-out), transform var(--duration-base) var(--ease-spring);
}
.onb__dot--active { background: var(--color-accent); transform: scale(1.4); }
.onb__actions { display: flex; gap: 12px; align-items: center; justify-content: space-between; width: 100%; padding-top: 8px; }

@media (prefers-reduced-motion: reduce) {
    .onb__dot { transition: none; }
}
</style>
