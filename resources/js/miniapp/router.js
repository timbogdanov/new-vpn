import { createRouter, createWebHashHistory } from 'vue-router';
import { BackButton } from './telegram.js';

import Home from './views/Home.vue';
import ServerList from './views/ServerList.vue';
import ServerDetail from './views/ServerDetail.vue';
import Profile from './views/Profile.vue';
import Tools from './views/Tools.vue';
import CensorshipProbe from './views/CensorshipProbe.vue';
import FreedomMap from './views/FreedomMap.vue';
import FreedomSearch from './views/FreedomSearch.vue';
import FreedomUrlDetail from './views/FreedomUrlDetail.vue';
import MyData from './views/MyData.vue';
import Paywall from './views/Paywall.vue';
import DevUI from './views/DevUI.vue';

const routes = [
    { path: '/', name: 'home', component: Home, meta: { title: 'Home' } },
    { path: '/servers', name: 'servers', component: ServerList, meta: { title: 'Servers' } },
    { path: '/servers/:slug', name: 'server-detail', component: ServerDetail, props: true, meta: { title: 'Server' } },
    { path: '/profile', name: 'profile', component: Profile, meta: { title: 'Profile' } },
    { path: '/profile/my-data', name: 'my-data', component: MyData, meta: { title: 'My data' } },
    { path: '/tools', name: 'tools', component: Tools, meta: { title: 'Tools' } },
    { path: '/tools/censorship', name: 'censorship', component: CensorshipProbe, meta: { title: 'Censorship' } },
    { path: '/tools/freedom', name: 'freedom', component: FreedomMap, meta: { title: 'Freedom' } },
    { path: '/tools/freedom/search', name: 'freedom-search', component: FreedomSearch, meta: { title: 'Search' } },
    { path: '/tools/freedom/url/:urlHash', name: 'freedom-url-detail', component: FreedomUrlDetail, props: true, meta: { title: 'Site' } },
    { path: '/paywall', name: 'paywall', component: Paywall, meta: { title: 'Upgrade' } },
    { path: '/dev/ui', name: 'dev-ui', component: DevUI, meta: { title: 'UI Primitives' } },
    { path: '/:pathMatch(.*)*', redirect: '/' },
];

export const router = createRouter({
    history: createWebHashHistory(),
    routes,
    scrollBehavior() {
        return { top: 0 };
    },
});

let backOff = null;

router.afterEach((to) => {
    if (backOff) { backOff(); backOff = null; }
    if (to.name !== 'home') {
        backOff = BackButton.show(() => router.back());
    }
});
