<script setup lang="ts">
import { computed, ref } from 'vue';

import { runGameAction } from '@/api/game';
import { usePlayerStore } from '@/stores/player';
import type { QuestEntry } from '@/types/game';

const player = usePlayerStore();
const busy = ref(false);
const message = ref<string | null>(null);

const questline = computed(() => player.state?.quests);
const quests = computed<QuestEntry[]>(() => {
    if (!questline.value) {
        return [];
    }

    return [
        ...questline.value.ready_to_claim,
        ...questline.value.active,
        ...questline.value.upcoming,
    ];
});
const biologistArea = computed(() => player.state?.map?.services?.find((service) => service.serviceType === 'biologist'));
const catalyst = computed(() => player.state?.inventory.find((item) => item.slug === 'research-catalyst'));
const report = computed(() => player.state?.inventory.find((item) => item.slug === 'research-report'));

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
        message.value = caught instanceof Error ? caught.message : 'Quest-Aktion fehlgeschlagen.';
    } finally {
        busy.value = false;
    }
}

async function claim(quest: QuestEntry): Promise<void> {
    if (!quest.character_quest_id) {
        return;
    }

    await act('claim-quest', { character_quest_id: quest.character_quest_id });
}

async function submit(quest: QuestEntry): Promise<void> {
    if (!quest.character_quest_id || !biologistArea.value) {
        return;
    }

    await act('submit-quest-item', {
        character_quest_id: quest.character_quest_id,
        location_area_id: biologistArea.value.id,
    });
}

async function boost(quest: QuestEntry): Promise<void> {
    if (!quest.character_quest_id || !biologistArea.value || !catalyst.value) {
        return;
    }

    await act('boost-research-submission', {
        character_quest_id: quest.character_quest_id,
        location_area_id: biologistArea.value.id,
        character_item_id: catalyst.value.id,
    });
}

async function skip(quest: QuestEntry): Promise<void> {
    if (!quest.character_quest_id || !biologistArea.value || !report.value) {
        return;
    }

    await act('skip-research-cooldown', {
        character_quest_id: quest.character_quest_id,
        location_area_id: biologistArea.value.id,
        character_item_id: report.value.id,
    });
}
</script>

<template>
    <div class="space-y-3">
        <p v-if="message" class="rounded border border-amber-500/25 bg-black/25 px-3 py-2 text-sm text-amber-100">
            {{ message }}
        </p>

        <article
            v-for="quest in quests"
            :key="`${quest.title}-${quest.character_quest_id ?? 'upcoming'}`"
            class="rounded border border-amber-500/20 bg-black/20 p-4"
        >
            <div class="flex items-start justify-between gap-3">
                <div>
                    <p class="hud-stat-label">{{ quest.status === 'ready_to_claim' ? 'Belohnung bereit' : 'Quest' }}</p>
                    <h3 class="font-display mt-1 text-lg text-amber-100">{{ quest.title }}</h3>
                </div>
                <span class="pixel-chip">{{ quest.status }}</span>
            </div>
            <p class="mt-2 text-sm leading-6 text-stone-400">{{ quest.description }}</p>
            <div class="mt-3 h-2 overflow-hidden rounded-full border border-black/40 bg-black/40">
                <div
                    class="h-full rounded-full bg-gradient-to-r from-amber-300 to-yellow-600"
                    :style="{ width: `${quest.progress_percent}%` }"
                />
            </div>
            <div class="mt-2 flex flex-wrap items-center justify-between gap-2 text-xs">
                <span class="text-stone-500">{{ quest.progress }} / {{ quest.objective_target }}</span>
                <span class="text-amber-200/75">
                    {{ quest.reward_experience }} EXP · {{ quest.reward_yang }} Yang
                    <template v-if="quest.reward_item_label"> · {{ quest.reward_item_quantity }}x {{ quest.reward_item_label }}</template>
                </span>
            </div>

            <div v-if="quest.character_quest_id" class="mt-3 flex flex-wrap gap-2">
                <button
                    v-if="quest.status === 'ready_to_claim'"
                    type="button"
                    class="pixel-button min-h-9 px-3 text-[0.6rem]"
                    :disabled="busy"
                    @click="claim(quest)"
                >
                    Belohnung abholen
                </button>
                <button
                    v-if="quest.status === 'active' && biologistArea && quest.description.toLowerCase().includes('biolog')"
                    type="button"
                    class="pixel-button pixel-button-muted min-h-9 px-3 text-[0.6rem]"
                    :disabled="busy"
                    @click="submit(quest)"
                >
                    Probe abgeben
                </button>
                <button
                    v-if="quest.status === 'active' && catalyst"
                    type="button"
                    class="pixel-button pixel-button-muted min-h-9 px-3 text-[0.6rem]"
                    :disabled="busy"
                    @click="boost(quest)"
                >
                    Katalysator
                </button>
                <button
                    v-if="quest.status === 'active' && report"
                    type="button"
                    class="pixel-button pixel-button-muted min-h-9 px-3 text-[0.6rem]"
                    :disabled="busy"
                    @click="skip(quest)"
                >
                    Bericht
                </button>
            </div>
        </article>

        <p v-if="quests.length === 0" class="text-sm text-stone-400">Keine Quests in dieser Region.</p>
    </div>
</template>
