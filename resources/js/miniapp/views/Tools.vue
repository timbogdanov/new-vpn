<script setup>
import { computed, ref } from 'vue';
import { Shield, ShieldOff, Gauge as GaugeIcon, Globe } from 'lucide-vue-next';

import { runIpCheck, runSpeedTest, toast } from '../store.js';
import { t } from '../i18n.js';
import { hap } from '../telegram.js';

import Gauge from '../components/Gauge.vue';
import FlagIcon from '../components/FlagIcon.vue';
import { Card, SectionLabel, Button, Chip } from '../components/ui/index.js';

const ipLoading = ref(false);
const ipResult = ref(null);
const speedLoading = ref(false);
const speedResult = ref(null);

const protectedChip = computed(() => {
    if (!ipResult.value) return null;
    return ipResult.value.isProtected
        ? { tone: 'success', label: t('tools.ipProtected') }
        : { tone: 'warning', label: t('tools.ipUnprotected') };
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
    <div class="px-4 space-y-6">
        <!-- IP / Protection -->
        <section>
            <SectionLabel>{{ t('tools.ipTitle') }}</SectionLabel>
            <Card>
                <div class="tool-head">
                    <div class="tool-head__icon" :data-state="ipResult?.isProtected ? 'ok' : 'warn'">
                        <Shield v-if="ipResult?.isProtected" :size="22" :stroke-width="1.75" />
                        <ShieldOff v-else :size="22" :stroke-width="1.75" />
                    </div>
                    <div class="tool-head__body">
                        <div class="tool-head__title">
                            {{ ipResult ? (ipResult.isProtected ? t('tools.ipProtected') : t('tools.ipUnprotected')) : t('tools.ipTitle') }}
                        </div>
                        <div class="tool-head__subtitle">
                            <template v-if="ipResult">
                                <Chip v-if="protectedChip" :tone="protectedChip.tone" dot>
                                    {{ protectedChip.label }}
                                </Chip>
                            </template>
                            <template v-else>
                                {{ t('tools.ipUnprotectedBody') }}
                            </template>
                        </div>
                    </div>
                </div>

                <div v-if="ipResult" class="tool-detail">
                    <div class="tool-detail__row">
                        <FlagIcon v-if="ipResult.countryCode" :code="ipResult.countryCode" :size="24" />
                        <Globe v-else :size="20" :stroke-width="1.75" class="tool-detail__globe" />
                        <span class="tool-detail__location">
                            <template v-if="ipResult.city">{{ ipResult.city }}, </template>
                            {{ ipResult.country || '—' }}
                        </span>
                    </div>
                    <div class="tool-detail__mono tabular-nums">
                        {{ ipResult.ip }}<span v-if="ipResult.isp"> · {{ ipResult.isp }}</span>
                    </div>
                </div>

                <Button class="tool-run" variant="secondary" block :loading="ipLoading" @click="checkIp">
                    {{ ipLoading ? t('tools.ipChecking') : t('tools.ipRun') }}
                </Button>
            </Card>
        </section>

        <!-- Speed test -->
        <section>
            <SectionLabel>{{ t('tools.speedTitle') }}</SectionLabel>
            <Card>
                <div class="tool-head">
                    <div class="tool-head__icon" data-state="accent">
                        <GaugeIcon :size="22" :stroke-width="1.75" />
                    </div>
                    <div class="tool-head__body">
                        <div class="tool-head__title">{{ t('tools.speedTitle') }}</div>
                        <div class="tool-head__subtitle">{{ t('tools.speedNote') }}</div>
                    </div>
                </div>

                <div v-if="speedResult" class="speed-grid">
                    <Gauge :value="speedResult.downloadMbps" :max="500" :label="t('tools.speedDownload')" unit="Mbps" :size="92" />
                    <Gauge :value="speedResult.uploadMbps"   :max="500" :label="t('tools.speedUpload')"   unit="Mbps" :size="92" />
                    <Gauge :value="speedResult.pingMs"       :max="300" :label="t('tools.speedPing')"     unit="ms"   :size="92" />
                </div>

                <Button class="tool-run" variant="secondary" block :loading="speedLoading" @click="speed">
                    {{ speedLoading ? t('tools.speedRunning') : t('tools.speedRun') }}
                </Button>
            </Card>
        </section>
    </div>
</template>

<style scoped>
.tool-head {
    display: flex;
    align-items: flex-start;
    gap: 14px;
    margin-bottom: 16px;
}
.tool-head__icon {
    width: 40px;
    height: 40px;
    border-radius: var(--radius-sm);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: var(--color-surface-raised);
    color: var(--color-text-subtle);
    flex-shrink: 0;
}
.tool-head__icon[data-state="ok"]     { color: var(--color-success); background: color-mix(in srgb, var(--color-success) 12%, transparent); }
.tool-head__icon[data-state="warn"]   { color: var(--color-warning); background: color-mix(in srgb, var(--color-warning) 12%, transparent); }
.tool-head__icon[data-state="accent"] { color: var(--color-accent);  background: color-mix(in srgb, var(--color-accent) 12%, transparent); }

.tool-head__body { flex: 1 1 auto; min-width: 0; }
.tool-head__title {
    font-size: 15px;
    line-height: 22px;
    font-weight: 600;
    color: var(--color-text);
}
.tool-head__subtitle {
    font-size: 13px;
    line-height: 18px;
    color: var(--color-text-hint);
    margin-top: 2px;
}

.tool-detail {
    margin-bottom: 16px;
    padding: 12px 14px;
    background: var(--color-surface-raised);
    border-radius: var(--radius-sm);
    font-size: 13px;
    line-height: 18px;
}
.tool-detail__row {
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--color-text);
    margin-bottom: 4px;
}
.tool-detail__location { font-weight: 500; }
.tool-detail__globe { color: var(--color-text-subtle); }
.tool-detail__mono {
    font-family: var(--font-mono);
    font-size: 12px;
    color: var(--color-text-hint);
    word-break: break-all;
}

.speed-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
    margin-bottom: 16px;
    justify-items: center;
}

.tool-run { margin-top: 4px; }
</style>
