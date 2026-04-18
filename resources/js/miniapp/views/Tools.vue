<script setup>
import { computed, ref } from 'vue';

import { runIpCheck, runSpeedTest, toast } from '../store.js';
import { t } from '../i18n.js';
import { hap } from '../telegram.js';

import Gauge from '../components/Gauge.vue';
import { Card, Button, Chip } from '../components/ui/index.js';

const ipLoading = ref(false);
const ipResult = ref(null);
const speedLoading = ref(false);
const speedResult = ref(null);

const ipStatus = computed(() => {
    if (!ipResult.value) return null;
    return ipResult.value.isProtected
        ? { tone: 'success', label: t('tools.ipProtected') }
        : { tone: 'danger', label: t('tools.ipUnprotected') };
});

async function checkIp() {
    hap.light();
    ipLoading.value = true;
    try { ipResult.value = await runIpCheck(); }
    catch { toast(t('common.error'), 'error'); }
    finally { ipLoading.value = false; }
}

async function speed() {
    hap.light();
    speedLoading.value = true;
    try { speedResult.value = await runSpeedTest(); }
    catch { toast(t('common.error'), 'error'); }
    finally { speedLoading.value = false; }
}
</script>

<template>
    <div class="tools">
        <section class="tools__section">
            <div class="tools__label">{{ t('tools.ipTitle') }}</div>
            <Card padding="lg">
                <div class="tools__head">
                    <p class="tools__title">
                        {{ ipResult ? (ipResult.isProtected ? t('tools.ipProtected') : t('tools.ipUnprotected')) : t('tools.ipTitle') }}
                    </p>
                    <p class="tools__desc">
                        {{ ipResult ? (ipResult.isProtected ? t('tools.ipProtectedBody') : t('tools.ipUnprotectedBody')) : t('tools.ipUnprotectedBody') }}
                    </p>
                </div>

                <div v-if="ipResult" class="tools__result">
                    <Chip v-if="ipStatus" :tone="ipStatus.tone" dot>{{ ipStatus.label }}</Chip>
                    <div class="tools__mono">
                        {{ ipResult.ip }}<span v-if="ipResult.isp"> · {{ ipResult.isp }}</span>
                    </div>
                    <div v-if="ipResult.country || ipResult.city" class="tools__location">
                        <template v-if="ipResult.city">{{ ipResult.city }}, </template>
                        {{ ipResult.country || '—' }}
                    </div>
                </div>

                <Button variant="secondary" block :loading="ipLoading" @click="checkIp">
                    {{ ipLoading ? t('tools.ipChecking') : t('tools.ipRun') }}
                </Button>
            </Card>
        </section>

        <section class="tools__section">
            <div class="tools__label">{{ t('tools.speedTitle') }}</div>
            <Card padding="lg">
                <div class="tools__head">
                    <p class="tools__title">{{ t('tools.speedTitle') }}</p>
                    <p class="tools__desc">{{ t('tools.speedNote') }}</p>
                </div>

                <div v-if="speedResult" class="tools__speed">
                    <div class="tools__speed-item">
                        <Gauge :value="speedResult.downloadMbps" :max="500" unit="Mbps" :size="96" />
                        <div class="tools__speed-label">{{ t('tools.speedDownload') }}</div>
                    </div>
                    <div class="tools__speed-item">
                        <Gauge :value="speedResult.uploadMbps" :max="500" unit="Mbps" :size="96" />
                        <div class="tools__speed-label">{{ t('tools.speedUpload') }}</div>
                    </div>
                    <div class="tools__speed-item">
                        <Gauge :value="speedResult.pingMs" :max="300" unit="ms" :size="96" />
                        <div class="tools__speed-label">{{ t('tools.speedPing') }}</div>
                    </div>
                </div>

                <Button variant="secondary" block :loading="speedLoading" @click="speed">
                    {{ speedLoading ? t('tools.speedRunning') : t('tools.speedRun') }}
                </Button>
            </Card>
        </section>
    </div>
</template>

<style scoped>
.tools {
    display: flex;
    flex-direction: column;
    gap: 24px;
    padding-bottom: 32px;
}

.tools__section {
    display: flex;
    flex-direction: column;
    gap: 8px;
}
.tools__label {
    font-size: 11px;
    line-height: 14px;
    font-weight: 600;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--color-text-hint);
    padding: 0 16px;
}

.tools__head {
    display: flex;
    flex-direction: column;
    gap: 4px;
    margin-bottom: 16px;
}
.tools__title {
    margin: 0;
    font-size: 17px;
    line-height: 24px;
    font-weight: 500;
    color: var(--color-text-strong);
}
.tools__desc {
    margin: 0;
    font-size: 13px;
    line-height: 18px;
    color: var(--color-text-subtle);
}

.tools__result {
    display: flex;
    flex-direction: column;
    gap: 6px;
    margin-bottom: 16px;
    padding: 14px 16px;
    background: var(--color-surface);
    border-radius: var(--radius-md);
}
.tools__mono {
    font-family: var(--font-mono);
    font-size: 13px;
    line-height: 18px;
    color: var(--color-text-strong);
    font-variant-numeric: tabular-nums;
    word-break: break-all;
}
.tools__location {
    font-size: 12px;
    line-height: 16px;
    color: var(--color-text-subtle);
}

.tools__speed {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
    margin-bottom: 16px;
    justify-items: center;
}
.tools__speed-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
}
.tools__speed-label {
    font-size: 11px;
    line-height: 14px;
    font-weight: 600;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--color-text-hint);
}
</style>
