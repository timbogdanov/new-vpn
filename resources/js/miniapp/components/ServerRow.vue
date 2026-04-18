<script setup>
import { computed } from 'vue';
import FlagIcon from './FlagIcon.vue';
import { ListRow, Chip } from './ui/index.js';
import { t } from '../i18n.js';

const props = defineProps({
    server: { type: Object, required: true },
    pingOverride: { type: Number, default: null },
});

defineEmits(['click']);

const isSoon = computed(() => !!props.server.isComingSoon);
const displayPing = computed(() => props.pingOverride ?? props.server.pingMs ?? null);

const loadLevel = computed(() => {
    const p = props.server.loadPercent ?? null;
    if (p === null) return 0;
    if (p >= 75) return 3;
    if (p >= 40) return 2;
    return 1;
});
</script>

<template>
    <ListRow
        :disabled="isSoon"
        :chevron="!isSoon"
        @click="$emit('click', server)"
    >
        <template #title>
            <span class="server-row__title">
                <FlagIcon :code="server.countryCode" :size="20" />
                <span class="server-row__name">{{ server.name }}</span>
            </span>
        </template>
        <template #subtitle>
            {{ [server.city, server.country].filter(Boolean).join(', ') }}
        </template>

        <template #trailing>
            <Chip v-if="isSoon" tone="neutral">{{ t('servers.comingSoon') }}</Chip>

            <template v-else>
                <span v-if="displayPing != null" class="server-row__ping tabular-nums">
                    {{ Math.round(displayPing) }}ms
                </span>
                <span class="server-row__load" :aria-label="`Load level ${loadLevel}`">
                    <span :class="['server-row__dot', loadLevel >= 1 ? 'is-on' : '']"></span>
                    <span :class="['server-row__dot', loadLevel >= 2 ? 'is-on' : '']"></span>
                    <span :class="['server-row__dot', loadLevel >= 3 ? 'is-on' : '']"></span>
                </span>
            </template>
        </template>
    </ListRow>
</template>

<style scoped>
.server-row__title {
    display: inline-flex;
    align-items: center;
    gap: 10px;
}
.server-row__name { color: var(--color-text-strong); font-weight: 500; }

.server-row__ping {
    font-family: var(--font-mono);
    font-size: 13px;
    font-weight: 500;
    color: var(--color-text-subtle);
}

.server-row__load {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    margin-left: 4px;
}
.server-row__dot {
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: var(--color-separator);
}
.server-row__dot.is-on { background: var(--color-text-subtle); }
</style>
