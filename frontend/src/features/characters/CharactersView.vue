<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';

import { createCharacter, deleteCharacter, fetchCharacters, selectCharacter as selectCharacterApi, updateCharacter } from '@/api/game';
import { usePlayerStore } from '@/stores/player';
import type { CharacterSummary } from '@/types/game';

const router = useRouter();
const player = usePlayerStore();
const characters = ref<CharacterSummary[]>([]);
const races = ref<Array<{ key: string; label: string; description: string }>>([]);
const activeId = ref<number | null>(null);
const maxCharacters = ref(4);
const name = ref('');
const race = ref('warrior');
const busy = ref(false);
const error = ref<string | null>(null);

const canCreate = computed(() => characters.value.length < maxCharacters.value);

async function load(): Promise<void> {
    try {
        const data = await fetchCharacters();
        characters.value = data.characters;
        races.value = data.races;
        activeId.value = data.activeCharacterId;
        maxCharacters.value = data.maxCharactersPerAccount;
        race.value = races.value[0]?.key ?? 'warrior';
    } catch (caught) {
        error.value = caught instanceof Error ? caught.message : 'Charaktere konnten nicht geladen werden.';
    }
}

async function select(character: CharacterSummary): Promise<void> {
    if (busy.value) {
        return;
    }

    busy.value = true;
    error.value = null;

    try {
        const response = await selectCharacterApi(character.id);

        if (response.state) {
            player.applyState(response.state);
        }

        await router.push({ name: 'map', params: { mapId: '1' } });
    } catch (caught) {
        error.value = caught instanceof Error ? caught.message : 'Charakter konnte nicht ausgewählt werden.';
    } finally {
        busy.value = false;
    }
}

async function create(): Promise<void> {
    if (busy.value || !canCreate.value) {
        return;
    }

    busy.value = true;
    error.value = null;

    try {
        const response = await createCharacter(name.value.trim(), race.value);

        if (response.state) {
            player.applyState(response.state);
        }

        await router.push({ name: 'map', params: { mapId: '1' } });
    } catch (caught) {
        error.value = caught instanceof Error ? caught.message : 'Charakter konnte nicht erstellt werden.';
    } finally {
        busy.value = false;
    }
}

async function rename(character: CharacterSummary): Promise<void> {
    const nextName = window.prompt('Neuer Charaktername', character.name)?.trim();

    if (!nextName || nextName === character.name || busy.value) {
        return;
    }

    busy.value = true;
    error.value = null;

    try {
        const response = await updateCharacter(character.id, nextName);

        if (response.state) {
            player.applyState(response.state);
        }

        await load();
    } catch (caught) {
        error.value = caught instanceof Error ? caught.message : 'Charakter konnte nicht umbenannt werden.';
    } finally {
        busy.value = false;
    }
}

async function remove(character: CharacterSummary): Promise<void> {
    if (busy.value || !window.confirm(`Soll ${character.name} wirklich gelöscht werden?`)) {
        return;
    }

    busy.value = true;
    error.value = null;

    try {
        const response = await deleteCharacter(character.id);

        if (response.state) {
            player.applyState(response.state);
        }

        await load();
    } catch (caught) {
        error.value = caught instanceof Error ? caught.message : 'Charakter konnte nicht gelöscht werden.';
    } finally {
        busy.value = false;
    }
}

onMounted(() => {
    void load();
});
</script>

<template>
    <section class="mx-auto max-w-4xl space-y-4">
        <header class="pixel-frame p-5">
            <p class="hud-stat-label">Charaktere</p>
            <h1 class="font-display mt-1 text-3xl text-amber-100">Charakterhalle</h1>
            <p class="mt-2 text-sm text-stone-400">Wähle einen Charakter oder erstelle einen neuen Slot.</p>
        </header>

        <p v-if="error" class="rounded border border-red-400/30 bg-red-950/40 px-3 py-2 text-sm text-red-200">{{ error }}</p>

        <div class="grid gap-3 sm:grid-cols-2">
            <article
                v-for="character in characters"
                :key="character.id"
                class="pixel-panel p-4 transition"
                :class="{ 'border-amber-300/60 bg-amber-950/15': character.id === activeId }"
            >
                <button type="button" class="w-full text-left" :disabled="busy" @click="select(character)">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-display text-lg text-amber-100">{{ character.name }}</p>
                            <p class="mt-1 text-xs uppercase tracking-widest text-stone-500">
                                Lv {{ character.level }} · {{ character.race }}
                            </p>
                        </div>
                        <span class="pixel-chip">{{ character.ongoingBattle ? 'Im Kampf' : 'Bereit' }}</span>
                    </div>
                    <p class="mt-2 text-sm text-stone-400">{{ character.location }}</p>
                </button>
                <div class="mt-3 flex gap-2">
                    <button type="button" class="pixel-button pixel-button-muted min-h-8 px-2.5 text-[0.58rem]" :disabled="busy" @click="rename(character)">
                        Umbenennen
                    </button>
                    <button type="button" class="pixel-button pixel-button-muted min-h-8 px-2.5 text-[0.58rem]" :disabled="busy" @click="remove(character)">
                        Löschen
                    </button>
                </div>
            </article>
        </div>

        <form v-if="canCreate" class="pixel-frame space-y-3 p-5" @submit.prevent="create">
            <p class="hud-stat-label">Neuer Charakter · {{ characters.length }} / {{ maxCharacters }}</p>
            <div class="grid gap-3 sm:grid-cols-2">
                <label class="block">
                    <span class="text-xs text-stone-400">Name</span>
                    <input
                        v-model="name"
                        type="text"
                        minlength="3"
                        maxlength="20"
                        required
                        class="mt-1 w-full rounded border border-amber-500/30 bg-black/30 px-3 py-2 text-sm text-stone-200 outline-none focus:border-amber-300/60"
                    />
                </label>
                <label class="block">
                    <span class="text-xs text-stone-400">Kampfrasse</span>
                    <select
                        v-model="race"
                        class="mt-1 w-full rounded border border-amber-500/30 bg-black/30 px-3 py-2 text-sm text-stone-200 outline-none focus:border-amber-300/60"
                    >
                        <option v-for="option in races" :key="option.key" :value="option.key">
                            {{ option.label }}
                        </option>
                    </select>
                </label>
            </div>
            <button type="submit" class="pixel-button" :disabled="busy || name.trim().length < 3">
                Charakter erstellen
            </button>
        </form>
    </section>
</template>
