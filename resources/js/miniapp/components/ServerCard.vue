<script setup>
import { computed } from 'vue';
import FlagIcon from './FlagIcon.vue';
import LoadBar from './LoadBar.vue';
import PingBadge from './PingBadge.vue';
import { t } from '../i18n.js';

const props = defineProps({
    server: { type: Object, required: true },
    compact: { type: Boolean, default: false },
    pingOverride: { type: Number, default: null },
});

const isSoon = computed(() => props.server.isComingSoon);
const tags = computed(() => Array.isArray(props.server.tags) ? props.server.tags : []);
const displayPing = computed(() => props.pingOverride ?? props.server.pingMs ?? null);

defineEmits(['click']);
</script>

<template>
    <button
        class="w-full card text-left px-4 py-3 flex items-center gap-3 transition"
        style="outline: none"
        :disabled="isSoon && !$attrs.clickable"
        :aria-disabled="isSoon"
        @click="$emit('click', server)"
    >
        <FlagIcon :emoji="server.flagEmoji" :code="server.countryCode" :size="compact ? 36 : 44" />
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
                <div class="truncate text-[15px] font-semibold">{{ server.name }}</div>
                <span v-for="tag in tags" :key="tag"
                      :class="['chip', tag === 'recommended' && 'chip--recommended', tag === 'coming-soon' && 'chip--coming']">
                    <template v-if="tag === 'recommended'">★ {{ t('home.recommended') }}</template>
                    <template v-else-if="tag === 'coming-soon'">{{ t('servers.comingSoon') }}</template>
                    <template v-else>{{ tag }}</template>
                </span>
            </div>
            <div class="mt-0.5 text-xs truncate" style="color: var(--color-text-hint)">
                {{ server.city ? server.city + ', ' : '' }}{{ server.country }}
            </div>
            <div class="mt-2 flex items-center gap-2">
                <PingBadge :ms="displayPing" />
                <LoadBar v-if="!isSoon" :percent="server.loadPercent" class="flex-1" />
            </div>
        </div>
        <div class="opacity-70" style="color: var(--color-text-hint); font-size: 18px">›</div>
    </button>
</template>
