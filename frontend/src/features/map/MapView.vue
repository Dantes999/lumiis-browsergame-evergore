<script setup lang="ts">
import { computed, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import { useMapStore } from '@/stores/map';
import { usePlayerStore } from '@/stores/player';
import type { MapRegion } from '@/types/game';

const route = useRoute();
const router = useRouter();
const map = useMapStore();
const player = usePlayerStore();

const mapId = computed(() => Number(route.params.mapId ?? 1));
const selected = computed(() => map.selectedRegion);

function regionTarget(region: MapRegion): string {
    return region.kind === 'city' ? `/city/${region.slug}` : `/region/${region.slug}`;
}

function openRegion(region: MapRegion): void {
    void router.push(regionTarget(region));
}

function select(region: MapRegion): void {
    map.selectRegion(region);
}

onMounted(() => {
    void map.loadMap(mapId.value);
});

watch(mapId, (value) => {
    void map.loadMap(value);
});

watch(
    () => player.state,
    (state) => {
        if (state && state.player === null && !player.requiresLogin) {
            void router.push({ name: 'characters' });
        }
    },
    { immediate: true },
);
</script>

<template>
    <section class="space-y-4">
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="hud-stat-label">Aktuelle Karte</p>
                <h1 class="font-display mt-1 text-3xl tracking-[0.08em] text-amber-100 sm:text-4xl">
                    {{ map.gameMap?.name ?? 'Karte wird geladen …' }}
                </h1>
                <p class="mt-1 text-sm text-stone-400">{{ map.gameMap?.subtitle }}</p>
            </div>
            <div class="flex gap-2">
                <span class="pixel-chip">Map-first</span>
                <span class="pixel-chip">Klick eine Region</span>
            </div>
        </header>

        <p v-if="map.error" class="rounded border border-red-400/30 bg-red-950/40 px-3 py-2 text-sm text-red-200">
            {{ map.error }}
        </p>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_22rem]">
            <div class="pixel-frame relative overflow-hidden">
                <div class="relative aspect-[16/10] min-h-72 w-full sm:min-h-96">
                    <img
                        v-if="map.gameMap"
                        :src="map.gameMap.image"
                        :alt="`Karte ${map.gameMap.name}`"
                        class="pixelated absolute inset-0 h-full w-full object-cover opacity-90"
                    />
                    <div
                        class="absolute inset-0 bg-[radial-gradient(circle_at_50%_40%,transparent_0%,rgba(11,5,4,0.18)_52%,rgba(11,5,4,0.74)_100%)]"
                    />

                    <button
                        v-for="region in map.regions"
                        :key="region.slug"
                        type="button"
                        class="map-hotspot"
                        :class="{ 'map-hotspot-active': selected?.slug === region.slug }"
                        :style="{
                            left: `${region.x}%`,
                            top: `${region.y}%`,
                            '--hotspot-accent': region.accent,
                        }"
                        :title="region.name"
                        :aria-label="region.name"
                        @click="select(region)"
                    >
                        <span class="sr-only">{{ region.name }}</span>
                    </button>

                    <div class="absolute bottom-3 left-3 right-3 flex flex-wrap items-center gap-2">
                        <span class="pixel-chip bg-black/55">Regionen {{ map.regions.length }}</span>
                        <span class="pixel-chip bg-black/55">Stadt + Jagdgebiete</span>
                    </div>
                </div>
            </div>

            <aside class="pixel-frame flex flex-col p-4">
                <template v-if="selected">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="hud-stat-label">{{ selected.kind === 'city' ? 'Stadt' : 'Jagdgebiet' }}</p>
                            <h2 class="font-display mt-1 text-2xl tracking-[0.06em] text-amber-100">
                                {{ selected.name }}
                            </h2>
                            <p class="mt-1 text-xs uppercase tracking-[0.16em] text-stone-500">
                                {{ selected.levelRange }}
                            </p>
                        </div>
                        <span class="h-3 w-3 rounded-full" :style="{ background: selected.accent }" />
                    </div>

                    <p class="mt-3 text-sm leading-6 text-stone-400">{{ selected.description }}</p>

                    <div class="mt-4 grid grid-cols-2 gap-2 text-xs">
                        <div class="rounded border border-amber-500/20 bg-black/20 p-2.5">
                            <p class="hud-stat-label">Typ</p>
                            <p class="mt-1 text-stone-200">{{ selected.kind === 'city' ? 'Sicherer Ort' : 'Monsterregion' }}</p>
                        </div>
                        <div class="rounded border border-amber-500/20 bg-black/20 p-2.5">
                            <p class="hud-stat-label">Level</p>
                            <p class="mt-1 text-stone-200">{{ selected.levelRange }}</p>
                        </div>
                    </div>

                    <button type="button" class="pixel-button mt-4 w-full" @click="openRegion(selected)">
                        {{ selected.kind === 'city' ? 'Stadt betreten' : 'Region öffnen' }}
                    </button>
                </template>
                <template v-else>
                    <p class="hud-stat-label">Kartenlegende</p>
                    <p class="mt-2 text-sm leading-6 text-stone-400">
                        Wähle einen Hotspot auf der Karte. Städte öffnen ihr NPC-Diorama, Monsterregionen zeigen ihre Pfade.
                    </p>
                    <ul class="mt-4 space-y-2">
                        <li
                            v-for="region in map.regions"
                            :key="region.slug"
                            class="flex items-center justify-between gap-2 rounded border border-amber-500/15 bg-black/15 px-3 py-2"
                        >
                            <button type="button" class="min-w-0 text-left" @click="select(region)">
                                <span class="block truncate text-sm text-stone-200">{{ region.name }}</span>
                                <span class="block text-[0.62rem] uppercase tracking-widest text-stone-500">
                                    {{ region.levelRange }}
                                </span>
                            </button>
                            <span class="h-2 w-2 shrink-0 rounded-full" :style="{ background: region.accent }" />
                        </li>
                    </ul>
                </template>
            </aside>
        </div>

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <button
                v-for="region in map.regions"
                :key="`card-${region.slug}`"
                type="button"
                class="pixel-panel group p-4 text-left transition hover:border-amber-300/50 hover:bg-amber-950/20"
                @click="select(region)"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="hud-stat-label">{{ region.kind === 'city' ? 'Stadt' : 'Region' }}</p>
                        <h3 class="font-display mt-1 text-lg text-amber-100">{{ region.name }}</h3>
                    </div>
                    <span class="h-2.5 w-2.5 rounded-full" :style="{ background: region.accent }" />
                </div>
                <p class="mt-2 line-clamp-2 text-sm leading-6 text-stone-400">{{ region.description }}</p>
                <p class="mt-3 text-[0.68rem] font-bold uppercase tracking-[0.16em] text-stone-500">
                    {{ region.levelRange }}
                </p>
            </button>
        </div>
    </section>
</template>
