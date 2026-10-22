<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute, useRouter } from 'vue-router';

import { runGameAction } from '@/api/game';
import PathCard from '@/components/PathCard.vue';
import PixelAvatar from '@/components/PixelAvatar.vue';
import StatBar from '@/components/StatBar.vue';
import { useCombatStore } from '@/stores/combat';
import { useMapStore } from '@/stores/map';
import { usePlayerStore } from '@/stores/player';
import type { RegionPath } from '@/types/game';

const route = useRoute();
const router = useRouter();
const map = useMapStore();
const combat = useCombatStore();
const player = usePlayerStore();
const entering = ref(false);
const busy = ref(false);
const message = ref<string | null>(null);

const region = computed(() => map.regionDetail);
const slug = computed(() => String(route.params.slug));

async function load(): Promise<void> {
    await map.loadRegion(slug.value);
}

async function enterPath(path: RegionPath): Promise<void> {
    if (entering.value || !region.value) {
        return;
    }

    entering.value = true;

    try {
        await combat.begin(region.value.areaId, path.encounter, slug.value, path.id);

        if (combat.combat) {
            await router.push({ name: 'combat', params: { id: String(combat.combat.id) } });
        }
    } finally {
        entering.value = false;
    }
}

async function act(action: string, payload: Record<string, unknown> = {}): Promise<void> {
    if (busy.value) {
        return;
    }

    busy.value = true;
    message.value = null;

    try {
        const response = await runGameAction(action, payload);
        message.value = response.message;

        if (response.state) {
            player.applyState(response.state);
        }
    } catch (caught) {
        message.value = caught instanceof Error ? caught.message : 'Aktion fehlgeschlagen.';
    } finally {
        busy.value = false;
    }
}

async function autoFight(): Promise<void> {
    if (!region.value) {
        return;
    }

    await act('fight-monsters', { location_area_id: region.value.areaId });
}

async function searchLumiis(): Promise<void> {
    if (!region.value) {
        return;
    }

    await act('search-lumiis', { location_area_id: region.value.areaId });
}

async function rest(minutes: number): Promise<void> {
    if (!region.value) {
        return;
    }

    await act('rest', { rest_minutes: minutes });
}

async function startBoss(): Promise<void> {
    await startManual('boss');
}

async function startManualLumiis(): Promise<void> {
    await startManual('lumiis');
}

async function startManual(encounter: 'lumiis' | 'boss'): Promise<void> {
    if (!region.value) {
        return;
    }

    const response = await runGameAction('start-manual-fight', {
        location_area_id: region.value.areaId,
        encounter_request: encounter,
    });

    if (response.state) {
        player.applyState(response.state);
    }

    if (response.state?.battle) {
        await router.push({ name: 'combat', params: { id: String(response.state.battle.id) } });
    } else {
        message.value = response.message;
    }
}

onMounted(() => {
    void load();
});

watch(slug, () => {
    void load();
});
</script>

<template>
    <section class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <RouterLink :to="{ name: 'map', params: { mapId: '1' } }" class="pixel-button pixel-button-muted min-h-9 px-3 text-[0.62rem]">
                ← Karte
            </RouterLink>
            <div class="flex gap-2">
                <span class="pixel-chip">Jagdgebiet</span>
                <span class="pixel-chip">{{ region?.levelRange ?? '…' }}</span>
            </div>
        </div>

        <p v-if="map.error" class="rounded border border-red-400/30 bg-red-950/40 px-3 py-2 text-sm text-red-200">
            {{ map.error }}
        </p>

        <template v-if="region">
            <header class="pixel-frame relative overflow-hidden p-5 sm:p-6">
                <div
                    class="pointer-events-none absolute inset-0 opacity-45"
                    :style="{
                        background:
                            region.background === 'forest'
                                ? 'radial-gradient(circle at 20% 20%, rgba(70, 112, 63, 0.55), transparent 45%), linear-gradient(135deg, rgba(55, 85, 48, 0.45), transparent 65%)'
                                : 'radial-gradient(circle at 78% 15%, rgba(99, 154, 168, 0.4), transparent 42%), linear-gradient(135deg, rgba(94, 126, 74, 0.4), transparent 65%)',
                    }"
                />
                <div class="relative flex flex-wrap items-end justify-between gap-4">
                    <div class="max-w-2xl">
                        <p class="hud-stat-label">Region · {{ region.mapSlug }}</p>
                        <h1 class="font-display mt-1 text-3xl tracking-[0.06em] text-amber-100 sm:text-4xl">
                            {{ region.name }}
                        </h1>
                        <p class="mt-2 text-sm leading-6 text-stone-300">{{ region.description }}</p>
                    </div>
                    <div class="grid min-w-48 grid-cols-2 gap-2 text-xs">
                        <div class="rounded border border-amber-500/20 bg-black/25 p-2.5">
                            <p class="hud-stat-label">Level</p>
                            <p class="mt-1 text-stone-200">{{ region.levelRange }}</p>
                        </div>
                        <div class="rounded border border-amber-500/20 bg-black/25 p-2.5">
                            <p class="hud-stat-label">Pfade</p>
                            <p class="mt-1 text-stone-200">{{ region.paths.length }}</p>
                        </div>
                    </div>
                </div>

                <div class="relative mt-5 max-w-xl">
                    <StatBar label="Boss-Druck" :value="region.pressure" :max="region.pressureTarget" tone="pressure" />
                </div>
            </header>

            <p v-if="message" class="rounded border border-amber-500/25 bg-black/25 px-3 py-2 text-sm text-amber-100">
                {{ message }}
            </p>

            <div class="pixel-panel flex flex-wrap items-center gap-2 p-3">
                <button type="button" class="pixel-button" :disabled="busy" @click="autoFight">
                    Auto Fight
                </button>
                <button type="button" class="pixel-button" :disabled="busy" @click="searchLumiis">
                    Lumiis suchen
                </button>
                <button type="button" class="pixel-button pixel-button-muted" :disabled="busy" @click="startManualLumiis">
                    Lumiis manuell
                </button>
                <button type="button" class="pixel-button pixel-button-muted" :disabled="busy" @click="rest(30)">
                    Rasten 30 min
                </button>
                <button type="button" class="pixel-button pixel-button-muted" :disabled="busy" @click="rest(60)">
                    Rasten 60 min
                </button>
                <button type="button" class="pixel-button pixel-button-muted" :disabled="busy" @click="rest(120)">
                    Rasten 120 min
                </button>
                <button
                    v-if="region.boss.unlocked"
                    type="button"
                    class="pixel-button border-red-300/50"
                    :disabled="busy"
                    @click="startBoss"
                >
                    Boss herausfordern
                </button>
                <span class="ml-auto text-[0.65rem] uppercase tracking-widest text-stone-500">
                    {{ region.previews ? 'Server-Vorschau aktiv' : 'Keine Vorschau' }}
                </span>
            </div>

            <div class="grid gap-3 lg:grid-cols-3">
                <PathCard
                    v-for="path in region.paths"
                    :key="path.id"
                    :path="path"
                    :busy="entering"
                    @enter="enterPath"
                />
            </div>

            <article class="pixel-panel flex flex-wrap items-center justify-between gap-4 p-4">
                <div class="flex items-center gap-3">
                    <PixelAvatar :name="region.boss.name" accent="#b74b3b" category="boss" size="lg" />
                    <div>
                        <p class="hud-stat-label">Boss-Seele</p>
                        <h2 class="font-display text-xl text-amber-100">{{ region.boss.name }}</h2>
                        <p class="text-xs text-stone-500">Level {{ region.boss.level }} · erscheint bei vollem Druck</p>
                    </div>
                </div>
                <div class="min-w-48">
                    <StatBar label="Druck" :value="region.boss.pressure" :max="region.boss.pressureTarget" tone="pressure" />
                    <p class="mt-2 text-right text-[0.65rem] uppercase tracking-widest text-stone-500">
                        {{ region.boss.unlocked ? 'Verfügbar' : 'Noch versiegelt' }}
                    </p>
                </div>
            </article>
        </template>

        <div v-else class="pixel-frame p-6 text-sm text-stone-400">Region wird geladen …</div>
    </section>
</template>
