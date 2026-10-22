import { createRouter, createWebHistory } from 'vue-router';

const router = createRouter({
    history: createWebHistory(import.meta.env.BASE_URL),
    routes: [
        {
            path: '/',
            redirect: { name: 'map', params: { mapId: '1' } },
        },
        {
            path: '/map/:mapId',
            name: 'map',
            component: () => import('@/features/map/MapView.vue'),
        },
        {
            path: '/characters',
            name: 'characters',
            component: () => import('@/features/characters/CharactersView.vue'),
        },
        {
            path: '/region/:slug',
            name: 'region',
            component: () => import('@/features/region/RegionView.vue'),
        },
        {
            path: '/city/:slug',
            name: 'city',
            component: () => import('@/features/city/CityView.vue'),
        },
        {
            path: '/combat/:id',
            name: 'combat',
            component: () => import('@/features/combat/CombatView.vue'),
        },
        {
            path: '/:pathMatch(.*)*',
            redirect: { name: 'map', params: { mapId: '1' } },
        },
    ],
    scrollBehavior: () => ({ top: 0 }),
});

export default router;
