<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { RouterLink, useRouter } from 'vue-router';

import { runGameAction } from '@/api/game';
import PixelAvatar from '@/components/PixelAvatar.vue';
import StatBar from '@/components/StatBar.vue';
import { useCombatStore } from '@/stores/combat';
import { usePlayerStore } from '@/stores/player';

const router = useRouter();
const combatStore = useCombatStore();
const player = usePlayerStore();
const logPanel = ref<HTMLElement | null>(null);

const combat = computed(() => combatStore.combat);
const aliveEnemies = computed(() => combatStore.aliveEnemies);
const finished = computed(() => combat.value?.finished ?? false);
const victory = computed(() => combat.value?.victory ?? false);

async function act(action: 'attack' | 'skill'): Promise<void> {
    const skillSlug = action === 'skill' ? combat.value?.skills?.[0]?.slug : undefined;
    await combatStore.act(action, skillSlug);
    await nextTick();
    logPanel.value?.scrollTo({ top: logPanel.value.scrollHeight, behavior: 'smooth' });
}

async function flee(): Promise<void> {
    if (combat.value && !combat.value.finished) {
        try {
            const response = await runGameAction('abandon-manual-fight', { battle_id: combat.value.id });

            if (response.state) {
                player.applyState(response.state);
            }
        } catch {
            // The local reset below still returns the player to the map.
        }
    }

    combatStore.reset();
    await router.push({ name: 'map', params: { mapId: '1' } });
}

watch(
    () => combat.value?.log.length,
    async () => {
        await nextTick();
        logPanel.value?.scrollTo({ top: logPanel.value.scrollHeight, behavior: 'smooth' });
    },
);

onMounted(() => {
    if (!combatStore.combat && player.state?.battle) {
        combatStore.hydrate(player.state.battle);
    }
});
</script>

<template>
    <section class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <button type="button" class="pixel-button pixel-button-muted min-h-9 px-3 text-[0.62rem]" @click="flee">
                ← Karte
            </button>
            <div class="flex gap-2">
                <span class="pixel-chip">Runde {{ combat?.round ?? 0 }}</span>
                <span class="pixel-chip">{{ aliveEnemies.length }} Gegner</span>
            </div>
        </div>

        <p v-if="combatStore.error" class="rounded border border-red-400/30 bg-red-950/40 px-3 py-2 text-sm text-red-200">
            {{ combatStore.error }}
        </p>

        <template v-if="combat">
            <header class="pixel-frame p-4">
                <div class="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p class="hud-stat-label">Kampf · {{ combat.regionSlug }}</p>
                        <h1 class="font-display mt-1 text-2xl text-amber-100">
                            {{ combat.enemies[0]?.name ?? 'Begegnung' }}
                        </h1>
                    </div>
                    <span class="pixel-chip">{{ combat.pathId }}</span>
                </div>
            </header>

            <div class="pixel-frame scene-grid relative overflow-hidden">
                <div class="relative flex min-h-64 items-end justify-between gap-3 p-4 sm:min-h-72 sm:p-6">
                    <div class="absolute inset-x-0 bottom-0 h-1/3 bg-[linear-gradient(180deg,transparent,rgba(15,8,5,0.85))]" />

                    <div class="relative z-10 flex flex-1 flex-col items-start gap-2">
                        <PixelAvatar :name="combat.player.name" accent="#b07a47" size="xl" />
                        <div class="w-full max-w-52 rounded border border-amber-500/20 bg-black/40 p-2">
                            <p class="truncate text-xs font-bold text-amber-100">{{ combat.player.name }}</p>
                            <StatBar
                                label="HP"
                                :value="combat.player.health"
                                :max="combat.player.maxHealth"
                                :show-numbers="false"
                            />
                            <div class="mt-1">
                                <StatBar label="SP" :value="combat.player.sp" :max="combat.player.maxSp" tone="sp" :show-numbers="false" />
                            </div>
                        </div>
                    </div>

                    <div class="relative z-10 flex flex-1 flex-col items-end gap-2">
                        <div class="flex flex-wrap items-end justify-end gap-2">
                            <div
                                v-for="enemy in combat.enemies"
                                :key="enemy.id"
                                class="flex flex-col items-center gap-1"
                                :class="{ 'opacity-35 grayscale': enemy.health <= 0 }"
                            >
                                <PixelAvatar :name="enemy.name" :accent="enemy.accent" :category="enemy.category" size="lg" />
                                <div class="w-28 rounded border border-amber-500/20 bg-black/40 px-1.5 py-1">
                                    <p class="truncate text-[0.58rem] text-center text-stone-200">{{ enemy.name }}</p>
                                    <div class="mt-1 h-1.5 overflow-hidden rounded-full bg-black/50">
                                        <div
                                            class="h-full rounded-full bg-gradient-to-r from-rose-400 to-red-700"
                                            :style="{ width: `${Math.round((enemy.health / enemy.maxHealth) * 100)}%` }"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_16rem]">
                <div class="pixel-frame flex flex-col overflow-hidden">
                    <div class="flex flex-wrap gap-2 border-b border-amber-500/20 p-3">
                        <button type="button" class="pixel-button flex-1" :disabled="finished || combatStore.loading" @click="act('attack')">
                            Angriff
                        </button>
                        <button
                            v-if="combat.skills?.length"
                            type="button"
                            class="pixel-button flex-1"
                            :disabled="finished || combatStore.loading"
                            @click="act('skill')"
                        >
                            {{ combat.skills[0]?.name ?? 'Skill' }}
                        </button>
                        <button type="button" class="pixel-button pixel-button-muted flex-1" disabled>
                            Item
                        </button>
                        <button type="button" class="pixel-button pixel-button-muted flex-1" :disabled="finished" @click="flee">
                            Flucht
                        </button>
                    </div>

                    <div ref="logPanel" class="pixel-scroll h-52 overflow-y-auto p-3 sm:h-60">
                        <p
                            v-for="(line, index) in combat.log"
                            :key="`${index}-${line}`"
                            class="border-b border-amber-500/10 py-1.5 text-sm leading-6 text-stone-300 last:border-0"
                        >
                            <span class="mr-2 text-[0.62rem] text-stone-600">{{ String(index + 1).padStart(2, '0') }}</span>
                            {{ line }}
                        </p>
                    </div>
                </div>

                <aside class="pixel-frame space-y-3 p-4">
                    <p class="hud-stat-label">Gegner</p>
                    <div v-for="enemy in combat.enemies" :key="`side-${enemy.id}`" class="rounded border border-amber-500/15 bg-black/20 p-2">
                        <div class="flex items-center justify-between gap-2">
                            <span class="truncate text-xs text-stone-200">{{ enemy.name }}</span>
                            <span class="text-[0.6rem] text-stone-500">Lv {{ enemy.level }}</span>
                        </div>
                        <div class="mt-1.5 h-1.5 overflow-hidden rounded-full bg-black/50">
                            <div
                                class="h-full rounded-full bg-gradient-to-r from-rose-400 to-red-700"
                                :style="{ width: `${Math.round((enemy.health / enemy.maxHealth) * 100)}%` }"
                            />
                        </div>
                    </div>
                </aside>
            </div>

            <div
                v-if="finished"
                class="pixel-frame flex flex-wrap items-center justify-between gap-3 border-amber-300/40 p-4"
            >
                <div>
                    <p class="hud-stat-label">Kampfergebnis</p>
                    <p class="font-display mt-1 text-2xl" :class="victory ? 'text-emerald-200' : 'text-red-200'">
                        {{ victory ? 'Sieg' : 'Niederlage' }}
                    </p>
                </div>
                <div class="flex gap-2">
                    <RouterLink :to="{ name: 'map', params: { mapId: '1' } }" class="pixel-button">
                        Zur Karte
                    </RouterLink>
                    <button type="button" class="pixel-button pixel-button-muted" @click="flee">
                        Kampf verlassen
                    </button>
                </div>
            </div>
        </template>

        <div v-else class="pixel-frame p-6 text-sm text-stone-400">
            Kein aktiver Kampf. Öffne eine Region und wähle einen Pfad.
            <RouterLink :to="{ name: 'map', params: { mapId: '1' } }" class="pixel-button ml-3">
                Zur Karte
            </RouterLink>
        </div>
    </section>
</template>
