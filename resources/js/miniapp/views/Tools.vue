<script setup>
import { ref } from 'vue';
import { runIpCheck, runSpeedTest, toast } from '../store.js';
import { t } from '../i18n.js';
import { hap } from '../telegram.js';
import Gauge from '../components/Gauge.vue';

const ipLoading = ref(false);
const ipResult = ref(null);
const speedLoading = ref(false);
const speedResult = ref(null);

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
    <div class="pt-1 space-y-4">
        <!-- IP / Protection -->
        <div class="card p-5">
            <div class="text-sm font-semibold mb-1">{{ t('tools.ipTitle') }}</div>
            <div v-if="!ipResult && !ipLoading" class="text-xs mb-4" style="color: var(--color-text-subtitle)">
                {{ t('tools.ipUnprotectedBody') }}
            </div>

            <div v-if="ipResult" class="mb-4 flex items-start gap-3">
                <div class="text-3xl">{{ ipResult.flag || '🌍' }}</div>
                <div class="flex-1 min-w-0">
                    <div class="text-base font-semibold">
                        <span v-if="ipResult.isProtected" style="color: var(--color-success)">
                            {{ t('tools.ipProtected') }}
                        </span>
                        <span v-else style="color: var(--color-destructive)">
                            {{ t('tools.ipUnprotected') }}
                        </span>
                    </div>
                    <div class="text-xs mt-1" style="color: var(--color-text-subtitle)">
                        {{ ipResult.city }}<span v-if="ipResult.city">, </span>{{ ipResult.country }}
                    </div>
                    <div class="text-xs font-mono mt-0.5" style="color: var(--color-text-hint)">
                        {{ ipResult.ip }} · {{ ipResult.isp }}
                    </div>
                </div>
            </div>

            <button class="btn btn--secondary w-full" :disabled="ipLoading" @click="checkIp">
                {{ ipLoading ? t('tools.ipChecking') : t('tools.ipRun') }}
            </button>
        </div>

        <!-- Speed test -->
        <div class="card p-5">
            <div class="text-sm font-semibold mb-1">{{ t('tools.speedTitle') }}</div>
            <div class="text-xs mb-4" style="color: var(--color-text-subtitle)">{{ t('tools.speedNote') }}</div>

            <div v-if="speedResult" class="grid grid-cols-3 gap-3 mb-4">
                <Gauge :value="speedResult.downloadMbps" :max="500" :label="t('tools.speedDownload')" unit="Mbps" />
                <Gauge :value="speedResult.uploadMbps"   :max="500" :label="t('tools.speedUpload')"   unit="Mbps" />
                <Gauge :value="speedResult.pingMs"       :max="300" :label="t('tools.speedPing')"     unit="ms" />
            </div>

            <button class="btn btn--secondary w-full" :disabled="speedLoading" @click="speed">
                {{ speedLoading ? t('tools.speedRunning') : t('tools.speedRun') }}
            </button>
        </div>
    </div>
</template>
