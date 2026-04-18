<script setup>
import { computed } from 'vue';
import FlagIcon from './FlagIcon.vue';
import { Chip } from './ui/index.js';
import { t } from '../i18n.js';

const props = defineProps({
    server: { type: Object, default: null },
    ping: { type: Number, default: null },
    loading: { type: Boolean, default: false },
});

const hasServer = computed(() => !!props.server);

const loadTone = computed(() => {
    const p = props.server?.loadPercent;
    if (p == null) return 'neutral';
    if (p >= 75) return 'warning';
    return 'success';
});

const loadLabel = computed(() => {
    const p = props.server?.loadPercent;
    if (p == null) return null;
    if (p >= 75) return t('server.loadBusy');
    if (p >= 40) return t('server.loadModerate');
    return t('server.loadLow');
});
</script>

<template>
    <section class="hero">
        <div class="hero__top">
            <FlagIcon v-if="hasServer" :code="server.countryCode" :size="44" />
            <div v-else class="hero__flag-skel"></div>
            <div class="hero__body">
                <div class="hero__label">{{ t('home.recommended') }}</div>
                <h1 class="hero__title">{{ hasServer ? server.name : '—' }}</h1>
            </div>
        </div>

        <div v-if="hasServer" class="hero__meta">
            <span v-if="ping != null" class="hero__ping tabular-nums">
                {{ Math.round(ping) }}<span class="hero__ping-unit">ms</span>
            </span>
            <span v-if="server.city || server.country" class="hero__loc">
                {{ [server.city, server.country].filter(Boolean).join(', ') }}
            </span>
            <Chip v-if="loadLabel" :tone="loadTone" dot>{{ loadLabel }}</Chip>
        </div>

        <slot name="action" />
    </section>
</template>

<style scoped>
.hero {
    position: relative;
    padding: 22px 20px 20px;
    background:
        radial-gradient(
            120% 140% at 0% 0%,
            color-mix(in oklch, var(--color-accent) 14%, transparent) 0%,
            transparent 55%
        ),
        var(--color-surface);
    border-radius: var(--radius-md);
    border: 1px solid var(--color-separator);
    display: flex;
    flex-direction: column;
    gap: 16px;
    overflow: hidden;
}

/* Thin accent band along the top — premium signal, color-neutral. */
.hero::before {
    content: '';
    position: absolute;
    inset: 0 0 auto 0;
    height: 1px;
    background: linear-gradient(
        90deg,
        transparent 0%,
        color-mix(in oklch, var(--color-accent) 55%, transparent) 50%,
        transparent 100%
    );
    pointer-events: none;
}

.hero__top {
    display: flex;
    align-items: center;
    gap: 14px;
}

.hero__flag-skel {
    width: 44px;
    height: 30px;
    border-radius: 6px;
    background: var(--color-surface-raised);
}

.hero__body { flex: 1 1 auto; min-width: 0; }

.hero__label {
    font-size: 11px;
    line-height: 14px;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: color-mix(in srgb, var(--color-text-hint) 85%, var(--color-accent));
    font-weight: 700;
}

.hero__title {
    margin: 4px 0 0;
    font-family: var(--font-display);
    font-size: 26px;
    line-height: 30px;
    font-weight: 700;
    letter-spacing: -0.015em;
    color: var(--color-text);
}

.hero__meta {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    font-size: 13px;
    line-height: 18px;
    color: var(--color-text-subtle);
}

.hero__ping {
    font-weight: 600;
    color: var(--color-text);
}
.hero__ping-unit {
    margin-left: 2px;
    font-weight: 400;
    color: var(--color-text-hint);
}

.hero__loc { color: var(--color-text-hint); }
</style>
