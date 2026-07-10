import { createApp } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';
import App from './App.vue';
import ClaimsList from './views/ClaimsList.vue';
import AddClaim from './views/AddClaim.vue';
import ClaimDetail from './views/ClaimDetail.vue';
import TripsList from './views/TripsList.vue';
import AddTrip from './views/AddTrip.vue';
import TripDetail from './views/TripDetail.vue';

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
            { path: '/trips', name: 'trips', component: TripsList },
            { path: '/trips/new', name: 'add-trip', component: AddTrip },
            { path: '/trips/:id', name: 'trip', component: TripDetail, props: true },
        ],
        scrollBehavior() {
            return { top: 0 };
        },
    });

    // The blade sidebar is outside this Vue island; keep its active state in
    // sync when the user navigates client-side between Claims and Trips.
    const NAV_ACTIVE = ['bg-blue-600/10', 'text-blue-400', 'shadow-[inset_3px_0_0_0_#2563eb]'];
    const NAV_IDLE = ['hover:bg-white/5', 'hover:text-slate-200', 'text-slate-400'];
    router.afterEach((to) => {
        const onTrips = to.path.startsWith('/trips');
        document.querySelectorAll('[data-spa-nav]').forEach((link) => {
            const active = link.dataset.spaNav === (onTrips ? 'trips' : 'disputes');
            link.classList.remove(...NAV_ACTIVE, ...NAV_IDLE);
            link.classList.add(...(active ? NAV_ACTIVE : NAV_IDLE));
            const icon = link.querySelector('svg, i');
            if (icon) {
                icon.classList.toggle('text-blue-400', active);
                icon.classList.toggle('text-slate-500', !active);
            }
        });
    });

    createApp(App).use(router).mount(el);
}
