import { computed, ref } from 'vue';
import { defineStore } from 'pinia';

import { fetchCity, fetchMap, fetchRegion } from '@/api/game';
import type { CityDetail, GameMap, MapRegion, RegionDetail } from '@/types/game';

export const useMapStore = defineStore('map', () => {
    const gameMap = ref<GameMap | null>(null);
    const selectedRegion = ref<MapRegion | null>(null);
    const regionDetail = ref<RegionDetail | null>(null);
    const cityDetail = ref<CityDetail | null>(null);
    const loading = ref(false);
    const error = ref<string | null>(null);

    const regions = computed(() => gameMap.value?.regions ?? []);
    const selectionOpen = computed(() => selectedRegion.value !== null);

    async function loadMap(mapId = 1): Promise<void> {
        if (gameMap.value?.id === mapId || loading.value) {
            return;
        }

        loading.value = true;
        error.value = null;

        try {
            gameMap.value = await fetchMap(mapId);
        } catch (caught) {
            error.value = caught instanceof Error ? caught.message : 'Karte konnte nicht geladen werden.';
        } finally {
            loading.value = false;
        }
    }

    function selectRegion(region: MapRegion | null): void {
        selectedRegion.value = region;
        regionDetail.value = null;
        cityDetail.value = null;
    }

    async function loadRegion(slug: string): Promise<RegionDetail> {
        loading.value = true;
        error.value = null;

        try {
            const region = await fetchRegion(slug);
            regionDetail.value = region;

            return region;
        } catch (caught) {
            error.value = caught instanceof Error ? caught.message : 'Region konnte nicht geladen werden.';
            throw caught;
        } finally {
            loading.value = false;
        }
    }

    async function loadCity(slug: string): Promise<CityDetail> {
        loading.value = true;
        error.value = null;

        try {
            const city = await fetchCity(slug);
            cityDetail.value = city;

            return city;
        } catch (caught) {
            error.value = caught instanceof Error ? caught.message : 'Stadt konnte nicht geladen werden.';
            throw caught;
        } finally {
            loading.value = false;
        }
    }

    return {
        gameMap,
        regions,
        selectedRegion,
        selectionOpen,
        regionDetail,
        cityDetail,
        loading,
        error,
        loadMap,
        selectRegion,
        loadRegion,
        loadCity,
    };
});
