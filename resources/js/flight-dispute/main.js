import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import App from './App.vue';
import ClaimsList from './views/ClaimsList.vue';
import AddClaim from './views/AddClaim.vue';
import ClaimDetail from './views/ClaimDetail.vue';

const el = document.getElementById('flight-dispute-app');

if (el) {
    const base = el.dataset.base
        ? new URL(el.dataset.base, window.location.origin).pathname
        : '/flight-disputes';

    const router = createRouter({
        history: createWebHistory(base),
        routes: [
            { path: '/', name: 'claims', component: ClaimsList },
            { path: '/claims/new', name: 'add-claim', component: AddClaim },
            { path: '/claims/:id', name: 'claim', component: ClaimDetail, props: true },
        ],
        scrollBehavior() {
            return { top: 0 };
        },
    });

    createApp(App).use(router).mount(el);
}
