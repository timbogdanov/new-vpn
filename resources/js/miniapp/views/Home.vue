<script setup>
import { computed } from 'vue';
import { useRouter } from 'vue-router';
import { Shield, Gauge as GaugeIcon, Globe2, User } from 'lucide-vue-next';

import { store } from '../store.js';
import { t } from '../i18n.js';
import { i18nState } from '../i18n.js';
import { hap } from '../telegram.js';

import Skeleton from '../components/Skeleton.vue';
import ServerStatusHero from '../components/ServerStatusHero.vue';
import {
    Button, SectionLabel, ListGroup, ListRow,
} from '../components/ui/index.js';

const router = useRouter();

const recommended = computed(() => store.recommendedServer);
const availableCount = computed(() => store.availableServers.length);

// BCP-47 locale for Intl APIs: the i18n bundle code is 'en' / 'ru'; we expand
// to 'ru-RU' / 'en-US' so the date formatter picks the culturally expected
// month names ("18 апр. 2026 г.") rather than the browser fallback.
const intlLocale = computed(() => (i18nState.locale === 'ru' ? 'ru-RU' : 'en-US'));

const memberSince = computed(() => {
    const raw = store.user?.memberSince;
    if (!raw) return '';
    const d = new Date(raw);
    if (Number.isNaN(d.getTime())) return '';
    return d.toLocaleDateString(intlLocale.value, { year: 'numeric', month: 'short', day: 'numeric' });
});

const accountSubtitle = computed(() => (
    memberSince.value ? `${t('profile.memberSince')} · ${memberSince.value}` : ''
));

function connect() {
    if (!recommended.value) return;
    hap.select();
    router.push(`/servers/${recommended.value.slug}`);
}

function openServers() {
    hap.select();
    router.push('/servers');
}
function openTools() {
    hap.select();
    router.push('/tools');
}
function openProfile() {
    hap.select();
    router.push('/profile');
}
</script>

<template>
    <div class="px-4 space-y-6">
        <!-- Hero -->
        <Skeleton v-if="!store.servers.length" :height="160" />
        <ServerStatusHero v-else :server="recommended">
            <template #action>
                <Button
                    variant="primary"
                    block
                    :disabled="!recommended"
                    @click="connect"
                >
                    {{ t('server.connect') }}
                </Button>
            </template>
        </ServerStatusHero>

        <!-- Servers -->
        <section>
            <SectionLabel>{{ t('servers.title') }}</SectionLabel>
            <ListGroup>
                <ListRow
                    :title="t('home.allServers')"
                    :subtitle="availableCount > 0 ? t('home.gateways', { n: availableCount }) : ''"
                    chevron
                    @click="openServers"
                >
                    <template #leading>
                        <Globe2 :size="20" :stroke-width="1.75" />
                    </template>
                </ListRow>
            </ListGroup>
        </section>

        <!-- Tools -->
        <section>
            <SectionLabel>{{ t('home.quickTools') }}</SectionLabel>
            <ListGroup>
                <ListRow :title="t('home.toolsIp')" chevron @click="openTools">
                    <template #leading>
                        <Shield :size="20" :stroke-width="1.75" />
                    </template>
                </ListRow>
                <ListRow :title="t('home.toolsSpeed')" chevron @click="openTools">
                    <template #leading>
                        <GaugeIcon :size="20" :stroke-width="1.75" />
                    </template>
                </ListRow>
            </ListGroup>
        </section>

        <!-- Account -->
        <section>
            <SectionLabel>{{ t('home.account') }}</SectionLabel>
            <ListGroup>
                <ListRow
                    :title="store.user?.displayName || t('profile.title')"
                    :subtitle="accountSubtitle"
                    chevron
                    @click="openProfile"
                >
                    <template #leading>
                        <User :size="20" :stroke-width="1.75" />
                    </template>
                </ListRow>
            </ListGroup>
        </section>
    </div>
</template>
