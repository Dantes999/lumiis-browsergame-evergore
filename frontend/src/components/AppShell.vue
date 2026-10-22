<script setup lang="ts">
import { computed, onMounted } from 'vue';
import { RouterLink } from 'vue-router';

import { LOGIN_URL } from '@/api/client';
import { runGameAction } from '@/api/game';
import CharacterWindow from '@/components/CharacterWindow.vue';
import ChatWindow from '@/components/ChatWindow.vue';
import GameWindow from '@/components/GameWindow.vue';
import InventoryWindow from '@/components/InventoryWindow.vue';
import QuestWindow from '@/components/QuestWindow.vue';
import StatBar from '@/components/StatBar.vue';
import { usePlayerStore } from '@/stores/player';
import { useUiStore } from '@/stores/ui';

const player = usePlayerStore();
const ui = useUiStore();

const windowTitle = computed(() => {
    switch (ui.activeWindow) {
        case 'character':
            return 'Charakter';
        case 'inventory':
            return 'Inventar';
        case 'quests':
            return 'Quests';
        case 'chat':
            return 'Chat';
        default:
            return '';
    }
});

const tutorial = computed(() => {
    const value = player.state?.tutorial as
        | {
              active?: boolean;
              step?: number;
              definition?: { title?: string; body?: string; next_label?: string; previous_label?: string };
              can_go_back?: boolean;
              can_go_next?: boolean;
          }
        | undefined;

    return value;
});

async function tutorialAction(action: 'tutorial-next' | 'tutorial-previous'): Promise<void> {
    try {
        const response = await runGameAction(action);

        if (response.state) {
            player.applyState(response.state);
        }
    } catch {
        // The tutorial action can be temporarily unavailable; the next state refresh shows why.
    }
}

onMounted(() => {
    void player.load();
});
</script>

<template>
    <div class="min-h-dvh">
        <header class="sticky top-0 z-30 border-b border-amber-500/25 bg-[#120805]/95 backdrop-blur">
            <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-3 px-3 py-2 sm:px-5">
                <RouterLink
                    :to="{ name: 'map', params: { mapId: '1' } }"
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded border border-amber-300/50 bg-gradient-to-b from-amber-700 to-amber-950 font-display text-lg text-amber-100 shadow-[0_4px_0_rgba(0,0,0,0.35)]"
                    aria-label="Zur Karte"
                >
                    L
                </RouterLink>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="font-display truncate text-sm tracking-[0.08em] text-amber-100 sm:text-base">
                            {{ player.name }}
                        </span>
                        <span class="pixel-chip">Lv {{ player.level }}</span>
                        <span v-if="player.snapshot" class="pixel-chip">
                            {{ player.snapshot.location.name }}
                        </span>
                    </div>

                    <div v-if="player.snapshot" class="mt-1 grid grid-cols-3 gap-2 sm:max-w-xl sm:grid-cols-4">
                        <StatBar
                            label="HP"
                            :value="player.snapshot.health"
                            :max="player.snapshot.maxHealth"
                            :show-numbers="false"
                        />
                        <StatBar
                            label="SP"
                            :value="player.snapshot.sp"
                            :max="player.snapshot.maxSp"
                            tone="sp"
                            :show-numbers="false"
                        />
                        <StatBar
                            label="Sta"
                            :value="player.snapshot.stamina"
                            :max="player.snapshot.maxStamina"
                            tone="stamina"
                            :show-numbers="false"
                        />
                        <div class="hidden items-center justify-end gap-2 sm:flex">
                            <span class="text-[0.65rem] font-bold text-amber-200">
                                {{ player.snapshot.yang }} Yang
                            </span>
                        </div>
                    </div>
                </div>

                <nav class="grid w-full grid-cols-3 gap-1.5 sm:flex sm:w-auto sm:items-center">
                    <RouterLink
                        :to="{ name: 'map', params: { mapId: '1' } }"
                        class="pixel-button min-h-9 px-2 text-[0.58rem] sm:px-3 sm:text-[0.62rem]"
                    >
                        Karte
                    </RouterLink>
                    <RouterLink
                        :to="{ name: 'characters' }"
                        class="pixel-button min-h-9 px-2 text-[0.58rem] sm:px-3 sm:text-[0.62rem]"
                    >
                        Slots
                    </RouterLink>
                    <button type="button" class="pixel-button min-h-9 px-2 text-[0.58rem] sm:px-3 sm:text-[0.62rem]" @click="ui.openWindow('character')">
                        Charakter
                    </button>
                    <button type="button" class="pixel-button min-h-9 px-2 text-[0.58rem] sm:px-3 sm:text-[0.62rem]" @click="ui.openWindow('inventory')">
                        Inventar
                    </button>
                    <button type="button" class="pixel-button min-h-9 px-2 text-[0.58rem] sm:px-3 sm:text-[0.62rem]" @click="ui.openWindow('quests')">
                        Quests
                    </button>
                    <button type="button" class="pixel-button min-h-9 px-2 text-[0.58rem] sm:px-3 sm:text-[0.62rem]" @click="ui.openWindow('chat')">
                        Chat
                    </button>
                </nav>
            </div>
        </header>

        <main class="mx-auto max-w-7xl px-3 py-4 sm:px-5 sm:py-6">
            <div v-if="player.requiresLogin" class="pixel-frame mx-auto max-w-xl p-6 text-center">
                <p class="hud-stat-label">Anmeldung erforderlich</p>
                <h1 class="font-display mt-2 text-2xl text-amber-100">Betritt die Welt von Lumiis</h1>
                <p class="mt-2 text-sm leading-6 text-stone-400">
                    Deine Session ist abgelaufen oder du bist noch nicht angemeldet.
                </p>
                <a :href="LOGIN_URL" class="pixel-button mt-5">Zum Login</a>
            </div>
            <p v-if="player.error" class="mb-3 rounded border border-red-400/30 bg-red-950/40 px-3 py-2 text-sm text-red-200">
                {{ player.error }}
            </p>
            <section
                v-if="tutorial?.active"
                class="mb-3 rounded border border-amber-300/40 bg-amber-950/25 p-3"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="hud-stat-label">Tutorial · Schritt {{ tutorial.step }}</p>
                        <h2 class="font-display mt-1 text-lg text-amber-100">{{ tutorial.definition?.title }}</h2>
                        <p class="mt-1 max-w-3xl text-sm leading-6 text-stone-300">{{ tutorial.definition?.body }}</p>
                    </div>
                    <div class="flex gap-2">
                        <button
                            v-if="tutorial.can_go_back"
                            type="button"
                            class="pixel-button pixel-button-muted min-h-8 px-2.5 text-[0.58rem]"
                            @click="tutorialAction('tutorial-previous')"
                        >
                            {{ tutorial.definition?.previous_label ?? 'Zurück' }}
                        </button>
                        <button
                            v-if="tutorial.can_go_next"
                            type="button"
                            class="pixel-button min-h-8 px-2.5 text-[0.58rem]"
                            @click="tutorialAction('tutorial-next')"
                        >
                            {{ tutorial.definition?.next_label ?? 'Weiter' }}
                        </button>
                    </div>
                </div>
            </section>
            <RouterLink
                v-if="player.state?.battle"
                :to="{ name: 'combat', params: { id: String(player.state.battle.id) } }"
                class="mb-3 flex flex-wrap items-center justify-between gap-2 rounded border border-amber-300/40 bg-amber-950/30 px-3 py-2 text-sm text-amber-100 transition hover:bg-amber-900/30"
            >
                <span>Aktiver Kampf: {{ player.state.battle.encounterName }} · Runde {{ player.state.battle.round }}</span>
                <span class="pixel-chip">Fortsetzen</span>
            </RouterLink>
            <slot v-if="!player.requiresLogin" />
        </main>

        <GameWindow
            v-if="ui.activeWindow"
            :title="windowTitle"
            kicker="Spielerfenster"
            :wide="ui.activeWindow === 'inventory' || ui.activeWindow === 'chat'"
            @close="ui.closeWindow()"
        >
            <CharacterWindow v-if="ui.activeWindow === 'character'" />
            <InventoryWindow v-else-if="ui.activeWindow === 'inventory'" />
            <QuestWindow v-else-if="ui.activeWindow === 'quests'" />
            <ChatWindow v-else-if="ui.activeWindow === 'chat'" />
        </GameWindow>
    </div>
</template>
