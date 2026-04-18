<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import {
    Button, IconButton, Card, SectionLabel, Chip,
    ListRow, ListGroup, SegmentedControl, PageHeader, EmptyState, Sheet,
} from '../components/ui/index.js';

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
    <div class="dev-ui px-4 space-y-6 pb-12">
        <PageHeader title="UI Primitives" subtitle="Dev-only preview">
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
            <SectionLabel>Buttons</SectionLabel>
            <Card>
                <div class="flex flex-wrap gap-3">
                    <Button variant="primary">Primary</Button>
                    <Button variant="secondary">Secondary</Button>
                    <Button variant="ghost">Ghost</Button>
                    <Button variant="destructive">Destructive</Button>
                </div>
                <div class="flex flex-wrap gap-3 mt-3">
                    <Button size="sm" variant="primary">Primary sm</Button>
                    <Button size="sm" variant="secondary">Secondary sm</Button>
                    <Button variant="primary" loading>Loading</Button>
                    <Button variant="secondary" disabled>Disabled</Button>
                </div>
                <div class="mt-3">
                    <Button variant="primary" block>Block primary</Button>
                </div>
            </Card>
        </section>

        <section>
            <SectionLabel>Icon buttons</SectionLabel>
            <Card>
                <div class="flex gap-3">
                    <IconButton aria-label="Copy" variant="ghost">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                    </IconButton>
                    <IconButton aria-label="QR" variant="filled">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/><path d="M14 14h3v3h-3zM21 14v7M14 21h3"/></svg>
                    </IconButton>
                </div>
            </Card>
        </section>

        <section>
            <SectionLabel>Chips</SectionLabel>
            <Card>
                <div class="flex flex-wrap gap-2">
                    <Chip>Neutral</Chip>
                    <Chip tone="accent" dot>Accent</Chip>
                    <Chip tone="success" dot>Low load</Chip>
                    <Chip tone="warning" dot>Busy</Chip>
                </div>
            </Card>
        </section>

        <section>
            <SectionLabel>Segmented control</SectionLabel>
            <Card>
                <SegmentedControl
                    v-model="sort"
                    :options="[
                        { value: 'recommended', label: 'Recommended' },
                        { value: 'ping', label: 'Ping' },
                        { value: 'load', label: 'Load' },
                    ]"
                    aria-label="Sort servers"
                />
                <div class="mt-4">
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
                <ListRow title="Servers" subtitle="Finland · 42 ms" chevron @click="() => {}">
                    <template #leading>
                        <span class="mono-flag">FI</span>
                    </template>
                </ListRow>
                <ListRow title="IP check" subtitle="See your current IP and region" chevron @click="() => {}">
                    <template #leading>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                    </template>
                </ListRow>
                <ListRow title="Speed test" subtitle="Measure download, upload, and ping" chevron @click="() => {}">
                    <template #leading>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 14l3-3"/><circle cx="12" cy="14" r="8"/><path d="M4 14a8 8 0 1 1 16 0"/></svg>
                    </template>
                </ListRow>
                <ListRow :interactive="false" title="Read-only row" subtitle="Not tappable" />
            </ListGroup>
        </section>

        <section>
            <SectionLabel>Empty state</SectionLabel>
            <Card padding="none">
                <EmptyState title="No servers available" body="We’ll light one up shortly.">
                    <template #icon>
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><line x1="2" y1="2" x2="22" y2="22"/></svg>
                    </template>
                    <template #action>
                        <Button variant="ghost" size="sm">Refresh</Button>
                    </template>
                </EmptyState>
            </Card>
        </section>

        <section>
            <SectionLabel>Sheet</SectionLabel>
            <Card>
                <Button @click="sheetOpen = true">Open sheet</Button>
            </Card>
            <Sheet v-model:open="sheetOpen" aria-label="Sheet demo">
                <h2 class="text-lg font-semibold mb-2">Example sheet</h2>
                <p class="text-sm" style="color: var(--color-text-hint)">Bottom sheets are used for non-Telegram-native confirms (QR display, rotate key).</p>
                <div class="mt-4 flex gap-2 justify-end">
                    <Button variant="ghost" @click="sheetOpen = false">Cancel</Button>
                    <Button variant="primary" @click="sheetOpen = false">Confirm</Button>
                </div>
            </Sheet>
        </section>
    </div>
</template>

<style scoped>
.mono-flag {
    width: 28px;
    height: 20px;
    border-radius: 3px;
    background: var(--color-surface-raised);
    color: var(--color-text-subtle);
    font-size: 11px;
    font-weight: 600;
    letter-spacing: 0.04em;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 0 0 1px var(--color-separator) inset;
}
</style>
