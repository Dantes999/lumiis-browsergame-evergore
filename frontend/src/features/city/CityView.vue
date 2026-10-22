<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, useRoute } from 'vue-router';

import { runGameAction } from '@/api/game';
import GameWindow from '@/components/GameWindow.vue';
import PixelAvatar from '@/components/PixelAvatar.vue';
import { useMapStore } from '@/stores/map';
import { usePlayerStore } from '@/stores/player';
import { useUiStore } from '@/stores/ui';
import type { CityNpc } from '@/types/game';

const route = useRoute();
const map = useMapStore();
const player = usePlayerStore();
const ui = useUiStore();
const selectedNpc = ref<CityNpc | null>(null);
const busy = ref(false);
const message = ref<string | null>(null);

const city = computed(() => map.cityDetail);
const slug = computed(() => String(route.params.slug));

async function load(): Promise<void> {
    await map.loadCity(slug.value);
}

function talkTo(npc: CityNpc): void {
    selectedNpc.value = npc;
    message.value = null;
}

async function buy(npc: CityNpc, shopItemId: number): Promise<void> {
    if (!npc.areaId || busy.value) {
        return;
    }

    busy.value = true;
    message.value = null;

    try {
        const response = await runGameAction('buy-item', {
            location_area_id: npc.areaId,
            shop_item_id: shopItemId,
            quantity: 1,
        });
        message.value = response.message;

        if (response.state) {
            player.applyState(response.state);
        }
    } catch (caught) {
        message.value = caught instanceof Error ? caught.message : 'Kauf fehlgeschlagen.';
    } finally {
        busy.value = false;
    }
}

function openQuests(): void {
    selectedNpc.value = null;
    ui.openWindow('quests');
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
                <span class="pixel-chip">Stadt</span>
                <span class="pixel-chip">Sicherer Ort</span>
            </div>
        </div>

        <p v-if="map.error" class="rounded border border-red-400/30 bg-red-950/40 px-3 py-2 text-sm text-red-200">
            {{ map.error }}
        </p>

        <template v-if="city">
            <header class="pixel-frame p-5 sm:p-6">
                <p class="hud-stat-label">Stadt · {{ city.mapSlug }}</p>
                <h1 class="font-display mt-1 text-3xl tracking-[0.06em] text-amber-100 sm:text-4xl">
                    {{ city.name }}
                </h1>
                <p class="mt-2 max-w-2xl text-sm leading-6 text-stone-300">{{ city.description }}</p>
            </header>

            <div class="pixel-frame relative overflow-hidden">
                <div class="scene-grid relative aspect-[16/8] min-h-72 bg-[linear-gradient(180deg,#2b1710_0%,#1b0d08_52%,#2b1c10_52%,#3b2816_100%)]">
                    <div class="absolute inset-x-0 top-0 h-1/2 bg-[radial-gradient(circle_at_70%_10%,rgba(215,180,106,0.18),transparent_42%)]" />
                    <div class="absolute inset-x-0 bottom-0 h-1/2 bg-[linear-gradient(180deg,rgba(64,40,22,0.35),rgba(30,15,8,0.8))]" />

                    <div class="absolute inset-x-0 bottom-4 h-16 bg-[repeating-linear-gradient(90deg,rgba(255,240,205,0.08)_0,rgba(255,240,205,0.08)_2px,transparent_2px,transparent_42px)]" />

                    <button
                        v-for="npc in city.npcs"
                        :key="npc.id"
                        type="button"
                        class="absolute flex -translate-x-1/2 -translate-y-1/2 flex-col items-center gap-1 rounded p-1 transition hover:bg-amber-200/10"
                        :style="{ left: `${npc.x}%`, top: `${npc.y}%` }"
                        :aria-label="`${npc.name} ansprechen`"
                        @click="talkTo(npc)"
                    >
                        <PixelAvatar :name="npc.name" :accent="npc.accent" category="npc" size="lg" />
                        <span class="rounded bg-black/55 px-1.5 py-0.5 text-[0.58rem] font-bold uppercase tracking-wider text-amber-100">
                            {{ npc.name }}
                        </span>
                        <span class="text-[0.55rem] text-stone-400">{{ npc.role }}</span>
                    </button>
                </div>
            </div>

            <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                <button
                    v-for="npc in city.npcs"
                    :key="`list-${npc.id}`"
                    type="button"
                    class="pixel-panel flex items-center gap-3 p-3 text-left transition hover:border-amber-300/40"
                    @click="talkTo(npc)"
                >
                    <PixelAvatar :name="npc.name" :accent="npc.accent" category="npc" size="sm" />
                    <span class="min-w-0">
                        <span class="block truncate text-sm text-stone-200">{{ npc.name }}</span>
                        <span class="block text-[0.62rem] uppercase tracking-widest text-stone-500">{{ npc.role }}</span>
                    </span>
                </button>
            </div>
        </template>

        <div v-else class="pixel-frame p-6 text-sm text-stone-400">Stadt wird geladen …</div>

        <GameWindow
            v-if="selectedNpc"
            :title="selectedNpc.name"
            :kicker="selectedNpc.role"
            @close="selectedNpc = null"
        >
            <div class="flex items-start gap-4">
                <PixelAvatar :name="selectedNpc.name" :accent="selectedNpc.accent" category="npc" size="xl" />
                <div class="min-w-0 flex-1">
                    <p class="text-sm leading-6 text-stone-300">„{{ selectedNpc.greeting }}“</p>

                    <p v-if="message" class="mt-3 rounded border border-amber-500/25 bg-black/25 px-3 py-2 text-sm text-amber-100">
                        {{ message }}
                    </p>

                    <div v-if="selectedNpc.action === 'shop' && selectedNpc.shopItems?.length" class="mt-4 space-y-2">
                        <p class="hud-stat-label">Angebote</p>
                        <div
                            v-for="shopItem in selectedNpc.shopItems"
                            :key="shopItem.id"
                            class="flex flex-wrap items-center justify-between gap-2 rounded border border-amber-500/15 bg-black/20 p-2.5"
                        >
                            <div>
                                <p class="text-sm text-stone-200">{{ shopItem.name }}</p>
                                <p class="text-[0.62rem] uppercase tracking-widest text-stone-500">
                                    {{ shopItem.price }} Yang · Lv {{ shopItem.requiredLevel }}
                                    <template v-if="shopItem.stock !== null"> · Bestand {{ shopItem.stock }}</template>
                                </p>
                            </div>
                            <button
                                type="button"
                                class="pixel-button min-h-8 px-2.5 text-[0.58rem]"
                                :disabled="busy"
                                @click="buy(selectedNpc, shopItem.id)"
                            >
                                Kaufen
                            </button>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                        <button
                            v-if="selectedNpc.action === 'quests'"
                            type="button"
                            class="pixel-button"
                            @click="openQuests"
                        >
                            Forschung ansehen
                        </button>
                        <button type="button" class="pixel-button pixel-button-muted" @click="selectedNpc = null">
                            Verlassen
                        </button>
                    </div>
                </div>
            </div>
        </GameWindow>
    </section>
</template>
