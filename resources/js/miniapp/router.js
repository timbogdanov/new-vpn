import { createRouter, createWebHashHistory } from 'vue-router';
import { BackButton } from './telegram.js';

import Home from './views/Home.vue';
import ServerList from './views/ServerList.vue';
import ServerDetail from './views/ServerDetail.vue';
import Profile from './views/Profile.vue';
import Tools from './views/Tools.vue';
import DevUI from './views/DevUI.vue';

const routes = [
    { path: '/', name: 'home', component: Home, meta: { title: 'Home' } },
    { path: '/servers', name: 'servers', component: ServerList, meta: { title: 'Servers' } },
    { path: '/servers/:slug', name: 'server-detail', component: ServerDetail, props: true, meta: { title: 'Server' } },
    { path: '/profile', name: 'profile', component: Profile, meta: { title: 'Profile' } },
    { path: '/tools', name: 'tools', component: Tools, meta: { title: 'Tools' } },
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
