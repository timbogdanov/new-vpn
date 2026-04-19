<script setup>
import { computed, ref } from 'vue';
import { useRouter } from 'vue-router';

import { runIpCheck, runSpeedTest, toast } from '../store.js';
import { t } from '../i18n.js';
import { hap } from '../telegram.js';
import { probeReachability } from '../composables/useProbe.js';

import { SectionLabel, ListGroup, ListRow, Chip } from '../components/ui/index.js';

const router = useRouter();

const ipLoading = ref(false);
const ipResult = ref(null);
const speedLoading = ref(false);
const speedResult = ref(null);

const privacyLoading = ref(false);
const privacyResult = ref(null); // { ip: 'pass'|'fail', webrtc: 'pass'|'fail', ipv6: 'pass'|'warn' }

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

const privacyScore = computed(() => {
    if (!privacyResult.value) return null;
    const passed = ['ip', 'webrtc', 'ipv6'].filter((k) => privacyResult.value[k] === 'pass').length;
    return { passed, total: 3 };
});
const privacyTone = computed(() => {
    if (!privacyScore.value) return 'neutral';
    if (privacyScore.value.passed === 3) return 'success';
    if (privacyScore.value.passed <= 1) return 'danger';
    return 'warning';
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

// Gather public-IP ICE candidates. RFC1918 / link-local / mDNS names are skipped.
async function detectWebrtcPublicIps(timeoutMs = 2500) {
    if (typeof RTCPeerConnection === 'undefined') return [];
    const pc = new RTCPeerConnection({ iceServers: [{ urls: 'stun:stun.l.google.com:19302' }] });
    const ips = new Set();
    const isPrivateV4 = (ip) => (
        /^10\./.test(ip)
        || /^192\.168\./.test(ip)
        || /^172\.(1[6-9]|2\d|3[01])\./.test(ip)
        || /^127\./.test(ip)
        || /^169\.254\./.test(ip)
    );
    const isV4 = (s) => /^(\d{1,3}\.){3}\d{1,3}$/.test(s);
    const isV6 = (s) => s.includes(':');

    pc.onicecandidate = (e) => {
        const cand = e.candidate?.candidate || '';
        if (!cand) return;
        const parts = cand.split(' ');
        const ip = parts[4];
        if (!ip || ip.endsWith('.local')) return;
        if (isV4(ip) && isPrivateV4(ip)) return;
        if (isV6(ip) && /^(fe80|fc|fd|::1)/i.test(ip)) return;
        ips.add(ip);
    };
    try {
        pc.createDataChannel('p');
        const offer = await pc.createOffer();
        await pc.setLocalDescription(offer);
        await new Promise((r) => setTimeout(r, timeoutMs));
    } finally {
        try { pc.close(); } catch (_) {}
    }
    return [...ips];
}

async function runPrivacy() {
    if (privacyLoading.value) return;
    hap.light();
    privacyLoading.value = true;
    try {
        const [ipRes, leakedIps, ipv6Reach] = await Promise.all([
            runIpCheck().catch(() => null),
            detectWebrtcPublicIps(2500).catch(() => []),
            probeReachability('https://ipv6.google.com/favicon.ico', 3000),
        ]);

        const ipPass = !!ipRes?.isProtected;
        const vpnIp = ipRes?.ip || '';
        const webrtcPass = leakedIps.length === 0 || (leakedIps.length === 1 && leakedIps[0] === vpnIp);
        const ipv6Pass = ipv6Reach === 'blocked';

        privacyResult.value = {
            ip: ipPass ? 'pass' : 'fail',
            webrtc: webrtcPass ? 'pass' : 'fail',
            ipv6: ipv6Pass ? 'pass' : 'warn',
        };
        ipResult.value = ipRes || ipResult.value;
    } finally {
        privacyLoading.value = false;
    }
}

function goCensorship() {
    hap.select();
    router.push('/tools/censorship');
}

function goFreedom() {
    hap.select();
    router.push('/tools/freedom');
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
            <SectionLabel>{{ t('tools.privacyTitle') }}</SectionLabel>
            <ListGroup>
                <ListRow v-if="privacyScore" :interactive="false">
                    <template #title>
                        {{ t('tools.privacyScore', { passed: privacyScore.passed, total: privacyScore.total }) }}
                    </template>
                    <template #trailing>
                        <Chip :tone="privacyTone" dot>
                            {{ privacyScore.passed }}/{{ privacyScore.total }}
                        </Chip>
                    </template>
                </ListRow>

                <ListRow v-if="privacyResult" :interactive="false">
                    <template #title>{{ t('tools.privacyIp') }}</template>
                    <template #trailing>
                        <Chip :tone="privacyResult.ip === 'pass' ? 'success' : 'danger'" dot>
                            {{ privacyResult.ip === 'pass' ? t('tools.privacyIpPass') : t('tools.privacyIpFail') }}
                        </Chip>
                    </template>
                </ListRow>
                <ListRow v-if="privacyResult" :interactive="false">
                    <template #title>{{ t('tools.privacyWebrtc') }}</template>
                    <template #trailing>
                        <Chip :tone="privacyResult.webrtc === 'pass' ? 'success' : 'danger'" dot>
                            {{ privacyResult.webrtc === 'pass' ? t('tools.privacyWebrtcPass') : t('tools.privacyWebrtcFail') }}
                        </Chip>
                    </template>
                </ListRow>
                <ListRow v-if="privacyResult" :interactive="false">
                    <template #title>{{ t('tools.privacyIpv6') }}</template>
                    <template #trailing>
                        <Chip :tone="privacyResult.ipv6 === 'pass' ? 'success' : 'warning'" dot>
                            {{ privacyResult.ipv6 === 'pass' ? t('tools.privacyIpv6Pass') : t('tools.privacyIpv6Warn') }}
                        </Chip>
                    </template>
                </ListRow>

                <ListRow chevron :disabled="privacyLoading" @click="runPrivacy">
                    <template #title>
                        {{ privacyLoading ? t('tools.privacyRunning') : t('tools.privacyRun') }}
                    </template>
                </ListRow>
            </ListGroup>
        </section>

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

        <section class="tools__section">
            <ListGroup>
                <ListRow chevron @click="goFreedom">
                    <template #title>{{ t('tools.freedomTitle') }}</template>
                    <template #subtitle>{{ t('tools.freedomEntry') }}</template>
                </ListRow>
                <ListRow chevron @click="goCensorship">
                    <template #title>{{ t('tools.censorshipTitle') }}</template>
                    <template #subtitle>{{ t('tools.censorshipIntro') }}</template>
                </ListRow>
            </ListGroup>
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
