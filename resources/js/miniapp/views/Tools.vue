<script setup>
import { computed, ref } from 'vue';

import { runIpCheck, runSpeedTest, toast } from '../store.js';
import { t } from '../i18n.js';
import { hap } from '../telegram.js';

import { SectionLabel, ListGroup, ListRow, Chip } from '../components/ui/index.js';

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

const ipMeta = computed(() => {
    if (!ipResult.value) return '';
    const loc = [ipResult.value.city, ipResult.value.country].filter(Boolean).join(', ');
    return [ipResult.value.isp, loc].filter(Boolean).join(' · ');
});

async function checkIp() {
    if (ipLoading.value) return;
    hap.light();
    ipLoading.value = true;
    try { ipResult.value = await runIpCheck(); }
    catch { toast(t('common.error'), 'error'); }
    finally { ipLoading.value = false; }
}

async function speed() {
    if (speedLoading.value) return;
    hap.light();
    speedLoading.value = true;
    try { speedResult.value = await runSpeedTest(); }
    catch { toast(t('common.error'), 'error'); }
    finally { speedLoading.value = false; }
}

function fmtMbps(v) {
    if (v == null) return '—';
    return `${Number(v).toFixed(1)} Mbps`;
}
function fmtMs(v) {
    if (v == null) return '—';
    return `${Math.round(v)} ms`;
}
</script>

<template>
    <div class="tools">
        <section class="tools__section">
            <SectionLabel>{{ t('tools.ipTitle') }}</SectionLabel>
            <ListGroup>
                <ListRow :interactive="false">
                    <template #title>
                        {{ ipResult
                            ? (ipResult.isProtected ? t('tools.ipProtected') : t('tools.ipUnprotected'))
                            : t('tools.ipTitle') }}
                    </template>
                    <template #subtitle>
                        {{ ipResult
                            ? (ipResult.isProtected ? t('tools.ipProtectedBody') : t('tools.ipUnprotectedBody'))
                            : t('tools.ipUnprotectedBody') }}
                    </template>
                    <template v-if="ipStatus" #trailing>
                        <Chip :tone="ipStatus.tone" dot>{{ ipStatus.label }}</Chip>
                    </template>
                </ListRow>

                <ListRow v-if="ipResult" :interactive="false">
                    <template #title>
                        <span class="tools__mono">{{ ipResult.ip }}</span>
                    </template>
                    <template v-if="ipMeta" #subtitle>{{ ipMeta }}</template>
                </ListRow>

                <ListRow chevron :disabled="ipLoading" @click="checkIp">
                    <template #title>
                        {{ ipLoading ? t('tools.ipChecking') : t('tools.ipRun') }}
                    </template>
                </ListRow>
            </ListGroup>
        </section>

        <section class="tools__section">
            <SectionLabel>{{ t('tools.speedTitle') }}</SectionLabel>
            <ListGroup>
                <ListRow :interactive="false">
                    <template #title>{{ t('tools.speedDownload') }}</template>
                    <template #trailing>
                        <span class="tools__mono">{{ fmtMbps(speedResult?.downloadMbps) }}</span>
                    </template>
                </ListRow>
                <ListRow :interactive="false">
                    <template #title>{{ t('tools.speedUpload') }}</template>
                    <template #trailing>
                        <span class="tools__mono">{{ fmtMbps(speedResult?.uploadMbps) }}</span>
                    </template>
                </ListRow>
                <ListRow :interactive="false">
                    <template #title>{{ t('tools.speedPing') }}</template>
                    <template #trailing>
                        <span class="tools__mono">{{ fmtMs(speedResult?.pingMs) }}</span>
                    </template>
                </ListRow>
                <ListRow chevron :disabled="speedLoading" @click="speed">
                    <template #title>
                        {{ speedLoading ? t('tools.speedRunning') : t('tools.speedRun') }}
                    </template>
                </ListRow>
            </ListGroup>
            <p class="tools__hint">{{ t('tools.speedNote') }}</p>
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

.tools__mono {
    font-family: var(--font-mono);
    font-size: 13px;
    line-height: 18px;
    color: var(--color-text-strong);
    font-variant-numeric: tabular-nums;
    font-weight: 500;
    word-break: break-all;
}

.tools__hint {
    margin: 0;
    padding: 0 16px;
    font-size: 12px;
    line-height: 16px;
    color: var(--color-text-hint);
}
</style>
