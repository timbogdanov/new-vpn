<script setup>
import { computed } from 'vue';
import { Clock } from 'lucide-vue-next';
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
        :title="server.name"
        :subtitle="[server.city, server.country].filter(Boolean).join(', ')"
        :disabled="isSoon"
        :chevron="!isSoon"
        @click="$emit('click', server)"
    >
        <template #leading>
            <FlagIcon :code="server.countryCode" :size="32" />
        </template>

        <template #trailing>
            <Chip v-if="isSoon" tone="neutral" dot>
                <Clock :size="10" :stroke-width="2" />
                {{ t('servers.comingSoon') }}
            </Chip>

            <template v-else>
                <span v-if="displayPing != null" class="server-row__ping tabular-nums">
                    {{ Math.round(displayPing) }}<span class="server-row__ping-unit">ms</span>
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
.server-row__ping {
    font-size: 13px;
    font-weight: 500;
    color: var(--color-text);
}
.server-row__ping-unit {
    margin-left: 2px;
    font-size: 11px;
    font-weight: 400;
    color: var(--color-text-hint);
}

.server-row__load {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    margin-left: 4px;
}
.server-row__dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: var(--color-gray-4);
    transition: background var(--duration-base) var(--ease-out);
}
.server-row__dot.is-on { background: var(--color-accent); }
</style>
