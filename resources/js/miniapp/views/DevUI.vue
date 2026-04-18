<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import {
    Button, IconButton, Card, SectionLabel, Chip,
    ListRow, ListGroup, SegmentedControl, PageHeader, EmptyState, Sheet,
} from '../components/ui/index.js';
import Skeleton from '../components/Skeleton.vue';
import Spinner from '../components/Spinner.vue';
import Toast from '../components/Toast.vue';

const theme = ref(document.documentElement.getAttribute('data-theme') || 'dark');
const sort = ref('recommended');
const lang = ref('en');
const sheetOpen = ref(false);

function setTheme(next) {
    theme.value = next;
    document.documentElement.setAttribute('data-theme', next);
}

onMounted(() => setTheme(theme.value));
onBeforeUnmount(() => setTheme('dark'));
</script>

<template>
    <div class="dev-ui">
        <PageHeader title="UI Primitives" subtitle="Quiet Premium · dev preview">
            <template #trailing>
                <SegmentedControl
                    v-model="theme"
                    :options="[{ value: 'dark', label: 'Dark' }, { value: 'light', label: 'Light' }]"
                    aria-label="Theme"
                    @update:model-value="setTheme"
                />
            </template>
        </PageHeader>

        <section>
            <SectionLabel>Typography</SectionLabel>
            <Card padding="lg">
                <div class="ty ty--display font-display">Instrument Serif 28</div>
                <div class="ty ty--title">Geist 600 · 20 · Title</div>
                <div class="ty ty--subtitle">Geist 500 · 17 · Subtitle</div>
                <div class="ty ty--body">Geist 400 · 15 · Body copy runs at this size and reads cleanly inside a Telegram WebView.</div>
                <div class="ty ty--label">Geist 500 · 13 · Label</div>
                <div class="ty ty--caption">Geist 400 · 12 · Caption</div>
                <div class="ty ty--mono">Geist Mono · 42ms · 1.8 GB · nl-ams-01</div>
            </Card>
        </section>

        <section>
            <SectionLabel>Buttons</SectionLabel>
            <Card padding="lg">
                <div class="row">
                    <Button variant="primary">Primary</Button>
                    <Button variant="secondary">Secondary</Button>
                    <Button variant="ghost">Ghost</Button>
                    <Button variant="destructive">Destructive</Button>
                </div>
                <div class="row row--mt">
                    <Button size="sm" variant="primary">Primary sm</Button>
                    <Button size="sm" variant="secondary">Secondary sm</Button>
                    <Button variant="primary" loading>Loading</Button>
                    <Button variant="secondary" disabled>Disabled</Button>
                </div>
                <div class="row row--mt">
                    <Button variant="primary" block>Block primary</Button>
                </div>
            </Card>
        </section>

        <section>
            <SectionLabel>Chips</SectionLabel>
            <Card padding="lg">
                <div class="row row--wrap">
                    <Chip>Neutral</Chip>
                    <Chip tone="accent" dot>Connected</Chip>
                    <Chip tone="success" dot>Low load</Chip>
                    <Chip tone="warning" dot>Trial</Chip>
                    <Chip tone="danger" dot>Expired</Chip>
                </div>
            </Card>
        </section>

        <section>
            <SectionLabel>Segmented control</SectionLabel>
            <Card padding="lg">
                <SegmentedControl
                    v-model="sort"
                    :options="[
                        { value: 'recommended', label: 'Recommended' },
                        { value: 'ping', label: 'Ping' },
                        { value: 'load', label: 'Load' },
                    ]"
                    aria-label="Sort servers"
                />
                <div class="row--mt">
                    <SegmentedControl
                        v-model="lang"
                        :options="[{ value: 'en', label: 'English' }, { value: 'ru', label: 'Русский' }]"
                        aria-label="Language"
                    />
                </div>
            </Card>
        </section>

        <section>
            <SectionLabel>List rows</SectionLabel>
            <ListGroup>
                <ListRow title="Amsterdam" subtitle="Netherlands · 42 ms" chevron @click="() => {}" />
                <ListRow title="IP check" subtitle="Verify the tunnel is active" chevron @click="() => {}" />
                <ListRow title="Speed test" subtitle="Download, upload, ping" chevron @click="() => {}" />
                <ListRow :interactive="false" title="Protocol">
                    <template #trailing>VLESS Reality</template>
                </ListRow>
                <ListRow title="Log out" destructive chevron @click="() => {}" />
            </ListGroup>
        </section>

        <section>
            <SectionLabel>Icon buttons (functional only)</SectionLabel>
            <Card padding="lg">
                <div class="row">
                    <IconButton aria-label="Copy" variant="ghost">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    </IconButton>
                    <IconButton aria-label="Close" variant="filled">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </IconButton>
                </div>
            </Card>
        </section>

        <section>
            <SectionLabel>Empty state</SectionLabel>
            <Card padding="none">
                <EmptyState title="No servers match your filter" body="Try a different sort or clear filters.">
                    <template #action>
                        <Button variant="ghost" size="sm">Clear filters</Button>
                    </template>
                </EmptyState>
            </Card>
        </section>

        <section>
            <SectionLabel>Loading</SectionLabel>
            <Card padding="lg">
                <Skeleton :height="80" />
                <div class="row row--mt">
                    <Spinner />
                    <Spinner :size="18" />
                </div>
            </Card>
        </section>

        <section>
            <SectionLabel>Sheet</SectionLabel>
            <Card padding="lg">
                <Button @click="sheetOpen = true">Open sheet</Button>
            </Card>
            <Sheet v-model:open="sheetOpen" aria-label="Sheet demo">
                <h2 class="demo-sheet__title font-display">Example sheet</h2>
                <p class="demo-sheet__body">Bottom sheets are used for non-Telegram-native confirms (QR, billing history, upgrade paywall).</p>
                <div class="demo-sheet__actions">
                    <Button variant="ghost" @click="sheetOpen = false">Cancel</Button>
                    <Button variant="primary" @click="sheetOpen = false">Confirm</Button>
                </div>
            </Sheet>
        </section>
    </div>
</template>

<style scoped>
.dev-ui {
    display: flex;
    flex-direction: column;
    gap: 24px;
    padding-bottom: 48px;
}

.row { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
.row--wrap { flex-wrap: wrap; }
.row--mt { margin-top: 12px; }

.ty { padding: 4px 0; color: var(--color-text-strong); }
.ty--display { font-size: 28px; line-height: 34px; }
.ty--title { font-size: 20px; line-height: 26px; font-weight: 600; }
.ty--subtitle { font-size: 17px; line-height: 24px; font-weight: 500; }
.ty--body { font-size: 15px; line-height: 22px; color: var(--color-text); }
.ty--label { font-size: 13px; line-height: 18px; font-weight: 500; color: var(--color-text-subtle); }
.ty--caption { font-size: 12px; line-height: 16px; color: var(--color-text-hint); }
.ty--mono { font-family: var(--font-mono); font-size: 13px; color: var(--color-text-subtle); }

.demo-sheet__title {
    margin: 0 0 8px;
    font-size: 24px;
    line-height: 30px;
    color: var(--color-text-strong);
}
.demo-sheet__body {
    margin: 0;
    color: var(--color-text-subtle);
    font-size: 15px;
    line-height: 22px;
}
.demo-sheet__actions {
    display: flex;
    gap: 8px;
    justify-content: flex-end;
    margin-top: 24px;
}
</style>
