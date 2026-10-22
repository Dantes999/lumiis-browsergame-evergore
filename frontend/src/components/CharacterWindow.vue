<script setup lang="ts">
import { computed, ref } from 'vue';

import { runGameAction } from '@/api/game';
import StatBar from '@/components/StatBar.vue';
import { usePlayerStore } from '@/stores/player';

const player = usePlayerStore();
const busy = ref(false);
const message = ref<string | null>(null);

const snapshot = computed(() => player.snapshot);
const skills = computed(() => player.state?.skills ?? []);
const skillGroups = computed(() => player.state?.skillGroups ?? []);
const stats = computed(() => [
    { label: 'Angriff', value: snapshot.value?.attack ?? 0 },
    { label: 'Verteidigung', value: snapshot.value?.defense ?? 0 },
    { label: 'Level', value: snapshot.value?.level ?? 1 },
    { label: 'Yang', value: snapshot.value?.yang ?? 0 },
]);
const attributes = [
    { key: 'vitality', label: 'Vitalität' },
    { key: 'intelligence', label: 'Intelligenz' },
    { key: 'strength', label: 'Stärke' },
    { key: 'dexterity', label: 'Geschick' },
];

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

async function allocate(attribute: string): Promise<void> {
    await act('allocate-status-point', { attribute });
}

async function chooseGroup(group: string): Promise<void> {
    await act('choose-skill-group', { skill_group: group });
}

async function learn(skillId: number): Promise<void> {
    await act('learn-skill', { skill_id: skillId });
}

async function advance(skillId: number): Promise<void> {
    await act('advance-skill-mastery', { skill_id: skillId });
}
</script>

<template>
    <div v-if="snapshot" class="space-y-4">
        <p v-if="message" class="rounded border border-amber-500/25 bg-black/25 px-3 py-2 text-sm text-amber-100">
            {{ message }}
        </p>

        <div class="flex items-center gap-4 rounded border border-amber-500/20 bg-black/20 p-4">
            <div class="monster-glyph h-16 w-16 border-amber-200/40 text-xl" style="--glyph-accent: #b07a47">
                {{ snapshot.name.slice(0, 2).toUpperCase() }}
            </div>
            <div class="min-w-0">
                <p class="font-display truncate text-xl text-amber-100">{{ snapshot.name }}</p>
                <p class="text-sm text-stone-400">
                    Level {{ snapshot.level }} · {{ snapshot.location.name }}
                </p>
                <p class="mt-1 text-xs text-stone-500">
                    {{ snapshot.experience }} / {{ snapshot.nextLevelExperience }} EXP
                </p>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <StatBar label="Leben" :value="snapshot.health" :max="snapshot.maxHealth" />
            <StatBar label="Ausdauer" :value="snapshot.stamina" :max="snapshot.maxStamina" tone="stamina" />
            <StatBar label="SP" :value="snapshot.sp" :max="snapshot.maxSp" tone="sp" />
            <div class="grid grid-cols-2 gap-2">
                <div v-for="stat in stats" :key="stat.label" class="rounded border border-amber-500/15 bg-black/20 p-3">
                    <p class="hud-stat-label">{{ stat.label }}</p>
                    <p class="mt-1 font-display text-lg text-amber-100">{{ stat.value }}</p>
                </div>
            </div>
        </div>

        <div v-if="(snapshot.unspentStatusPoints ?? 0) > 0" class="rounded border border-amber-500/15 bg-black/20 p-4">
            <p class="hud-stat-label">Statuspunkte · {{ snapshot.unspentStatusPoints }}</p>
            <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-4">
                <button
                    v-for="attribute in attributes"
                    :key="attribute.key"
                    type="button"
                    class="pixel-button min-h-9 px-2 text-[0.6rem]"
                    :disabled="busy"
                    @click="allocate(attribute.key)"
                >
                    + {{ attribute.label }}
                </button>
            </div>
        </div>

        <div v-if="skillGroups.length > 0" class="rounded border border-amber-500/15 bg-black/20 p-4">
            <p class="hud-stat-label">Kampfschule wählen</p>
            <div class="mt-2 grid gap-2 sm:grid-cols-2">
                <button
                    v-for="group in skillGroups"
                    :key="group.key"
                    type="button"
                    class="pixel-panel p-3 text-left transition hover:border-amber-300/40"
                    :disabled="busy"
                    @click="chooseGroup(group.key)"
                >
                    <span class="font-display block text-amber-100">{{ group.label }}</span>
                    <span class="mt-1 block text-xs leading-5 text-stone-400">{{ group.description }}</span>
                </button>
            </div>
        </div>

        <div v-if="skills.length > 0" class="rounded border border-amber-500/15 bg-black/20 p-4">
            <p class="hud-stat-label">Fertigkeiten</p>
            <div class="mt-2 space-y-2">
                <div
                    v-for="skill in skills"
                    :key="skill.id"
                    class="flex flex-wrap items-center justify-between gap-2 rounded border border-amber-500/15 bg-stone-950/30 p-2.5"
                >
                    <div>
                        <p class="text-sm text-stone-200">{{ skill.name }}</p>
                        <p class="text-[0.62rem] uppercase tracking-widest text-stone-500">
                            Lv {{ skill.requiredLevel }} · {{ skill.learned ? skill.stage : 'Nicht gelernt' }}
                        </p>
                    </div>
                    <button
                        v-if="skill.canLearn"
                        type="button"
                        class="pixel-button min-h-8 px-2.5 text-[0.58rem]"
                        :disabled="busy"
                        @click="learn(skill.id)"
                    >
                        Lernen
                    </button>
                    <button
                        v-else-if="skill.canAdvance"
                        type="button"
                        class="pixel-button pixel-button-muted min-h-8 px-2.5 text-[0.58rem]"
                        :disabled="busy"
                        @click="advance(skill.id)"
                    >
                        Verbessern
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
