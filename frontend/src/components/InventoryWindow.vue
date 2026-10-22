<script setup lang="ts">
import { computed, ref } from 'vue';

import { runGameAction } from '@/api/game';
import { usePlayerStore } from '@/stores/player';
import type { InventoryItem } from '@/types/game';

const player = usePlayerStore();
const selectedId = ref<number | null>(null);
const busy = ref(false);
const message = ref<string | null>(null);
const moveColumn = ref(0);
const moveRow = ref(0);
const lockedBonusId = ref<number | null>(null);

const inventory = computed(() => player.state?.inventory ?? []);
const selected = computed(() => inventory.value.find((item) => item.id === selectedId.value) ?? null);
const shopArea = computed(() => player.state?.map?.services?.find((service) => ['shop', 'weapon-shop', 'armor-shop'].includes(service.serviceType ?? '')));
const smithArea = computed(() => player.state?.map?.services?.find((service) => service.serviceType === 'smith'));
const imprint = computed(() => player.state?.inventory.find((item) => item.slug === 'soul-imprint'));
const reweaver = computed(() => player.state?.inventory.find((item) => item.slug === 'soul-reweaver'));
const prism = computed(() => player.state?.inventory.find((item) => item.slug === 'soul-prism'));
const soulAnchor = computed(() => player.state?.inventory.find((item) => item.slug === 'soul-anchor'));

function iconFor(item: InventoryItem): string | null {
    return item.icon ? item.icon.replace(/^\/+/, '/') : null;
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

async function useItem(useUntilFull = false): Promise<void> {
    if (!selected.value) {
        return;
    }

    await act('use-item', {
        character_item_id: selected.value.id,
        quantity: 1,
        use_until_full: useUntilFull,
    });
}

async function equipItem(): Promise<void> {
    if (!selected.value) {
        return;
    }

    await act('equip-item', { character_item_id: selected.value.id });
}

async function unequipItem(): Promise<void> {
    if (!selected.value) {
        return;
    }

    await act('unequip-item', { character_item_id: selected.value.id });
}

async function sellItem(): Promise<void> {
    if (!selected.value || !shopArea.value) {
        message.value = 'Kein Händler in dieser Stadt gefunden.';
        return;
    }

    await act('sell-item', {
        character_item_id: selected.value.id,
        quantity: selected.value.quantity,
        location_area_id: shopArea.value.id,
    });
}

async function upgradeItem(): Promise<void> {
    if (!selected.value || !smithArea.value) {
        message.value = 'Kein Schmied in dieser Stadt gefunden.';
        return;
    }

    await act('upgrade-item', {
        character_item_id: selected.value.id,
        location_area_id: smithArea.value.id,
    });
}

async function addBonus(): Promise<void> {
    if (!selected.value || !imprint.value) {
        return;
    }

    await act('add-item-bonus', {
        target_character_item_id: selected.value.id,
        character_item_id: imprint.value.id,
    });
}

async function rerollBonuses(): Promise<void> {
    if (!selected.value || !reweaver.value) {
        return;
    }

    await act('reroll-item-bonuses', {
        target_character_item_id: selected.value.id,
        character_item_id: reweaver.value.id,
        ...(lockedBonusId.value !== null && soulAnchor.value ? { locked_bonus_id: lockedBonusId.value } : {}),
    });
}

async function moveItem(): Promise<void> {
    if (!selected.value) {
        return;
    }

    await act('move-inventory-item', {
        character_item_id: selected.value.id,
        inventory_column: moveColumn.value,
        inventory_row: moveRow.value,
    });
}

function selectItem(item: InventoryItem): void {
    selectedId.value = item.id;
    moveColumn.value = item.column ?? 0;
    moveRow.value = item.row ?? 0;
    lockedBonusId.value = null;
}

async function unlockBonus(): Promise<void> {
    if (!selected.value || !prism.value) {
        return;
    }

    await act('unlock-item-bonus', {
        target_character_item_id: selected.value.id,
        character_item_id: prism.value.id,
    });
}
</script>

<template>
    <div class="space-y-4">
        <p v-if="message" class="rounded border border-amber-500/25 bg-black/25 px-3 py-2 text-sm text-amber-100">
            {{ message }}
        </p>

        <div class="flex items-center justify-between gap-3">
            <p class="text-sm text-stone-400">{{ inventory.length }} Items in der Tasche</p>
            <span class="pixel-chip">{{ player.snapshot?.yang ?? 0 }} Yang</span>
        </div>

        <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_19rem]">
            <div class="grid grid-cols-4 content-start gap-2 sm:grid-cols-6">
                <button
                    v-for="item in inventory"
                    :key="item.id"
                    type="button"
                    class="flex aspect-square flex-col items-center justify-center gap-1 rounded border bg-black/25 p-1 text-center transition"
                    :class="selectedId === item.id ? 'border-amber-300/70 bg-amber-950/20' : 'border-amber-500/20 hover:border-amber-300/40'"
                    :title="item.name"
                    @click="selectItem(item)"
                >
                    <img
                        v-if="iconFor(item)"
                        :src="iconFor(item) ?? undefined"
                        :alt="item.name"
                        class="pixelated h-8 w-8 object-contain"
                    />
                    <span v-else class="text-lg font-display text-amber-100">{{ item.name.slice(0, 2) }}</span>
                    <span class="line-clamp-2 text-[0.56rem] leading-tight text-stone-300">{{ item.name }}</span>
                    <span class="text-[0.56rem] font-bold text-amber-200">x{{ item.quantity }}</span>
                </button>
                <div
                    v-for="index in Math.max(0, 24 - inventory.length)"
                    :key="`empty-${index}`"
                    class="aspect-square rounded border border-dashed border-stone-700/40 bg-black/10"
                />
            </div>

            <aside class="pixel-panel h-fit p-4">
                <template v-if="selected">
                    <p class="hud-stat-label">{{ selected.type }} · {{ selected.slot ?? 'Item' }}</p>
                    <h3 class="font-display mt-1 text-lg text-amber-100">{{ selected.name }}</h3>
                    <p class="mt-2 text-sm leading-6 text-stone-400">{{ selected.description }}</p>

                    <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                        <div v-if="selected.stats.attack" class="rounded border border-amber-500/15 bg-black/20 p-2">
                            +{{ selected.stats.attack }} Angriff
                        </div>
                        <div v-if="selected.stats.defense" class="rounded border border-amber-500/15 bg-black/20 p-2">
                            +{{ selected.stats.defense }} Verteidigung
                        </div>
                        <div v-if="selected.stats.health" class="rounded border border-amber-500/15 bg-black/20 p-2">
                            +{{ selected.stats.health }} HP
                        </div>
                        <div v-if="selected.stats.heal" class="rounded border border-amber-500/15 bg-black/20 p-2">
                            +{{ selected.stats.heal }} Heilung
                        </div>
                    </div>

                    <div v-if="selected.bonuses.length" class="mt-3 space-y-1">
                        <p class="hud-stat-label">Boni</p>
                        <p v-for="bonus in selected.bonuses" :key="bonus.id" class="text-xs text-stone-300">
                            {{ bonus.label }}
                        </p>
                    </div>

                    <div class="mt-4 grid gap-2">
                        <button
                            v-if="['consumable', 'skill-book'].includes(selected.type)"
                            type="button"
                            class="pixel-button"
                            :disabled="busy"
                            @click="useItem(false)"
                        >
                            Benutzen
                        </button>
                        <button
                            v-if="selected.type === 'consumable'"
                            type="button"
                            class="pixel-button pixel-button-muted"
                            :disabled="busy"
                            @click="useItem(true)"
                        >
                            Benutzen bis voll
                        </button>
                        <button
                            v-if="selected.slot && !selected.equipped"
                            type="button"
                            class="pixel-button"
                            :disabled="busy"
                            @click="equipItem"
                        >
                            Ausrüsten
                        </button>
                        <button
                            v-if="selected.equipped"
                            type="button"
                            class="pixel-button pixel-button-muted"
                            :disabled="busy"
                            @click="unequipItem"
                        >
                            Ablegen
                        </button>
                        <button
                            v-if="!selected.equipped && selected.sellPrice > 0"
                            type="button"
                            class="pixel-button pixel-button-muted"
                            :disabled="busy"
                            @click="sellItem"
                        >
                            Verkaufen ({{ selected.sellPrice * selected.quantity }} Yang)
                        </button>
                        <button
                            v-if="selected.slot && selected.slot !== 'mount' && !selected.equipped"
                            type="button"
                            class="pixel-button pixel-button-muted"
                            :disabled="busy"
                            @click="upgradeItem"
                        >
                            Schmieden (+{{ selected.upgradeLevel + 1 }})
                        </button>
                        <button
                            v-if="selected.slot && imprint"
                            type="button"
                            class="pixel-button pixel-button-muted"
                            :disabled="busy"
                            @click="addBonus"
                        >
                            Bonus hinzufügen
                        </button>
                        <label v-if="selected.slot && reweaver && selected.bonuses.length > 1 && soulAnchor" class="block">
                            <span class="text-[0.62rem] uppercase tracking-widest text-stone-500">Bonus behalten (Soul Anchor)</span>
                            <select
                                v-model.number="lockedBonusId"
                                class="mt-1 w-full rounded border border-amber-500/30 bg-black/30 px-2 py-1.5 text-xs text-stone-200"
                            >
                                <option :value="null">Keinen behalten</option>
                                <option v-for="bonus in selected.bonuses" :key="`lock-${bonus.id}`" :value="bonus.id">
                                    {{ bonus.label }}
                                </option>
                            </select>
                        </label>
                        <button
                            v-if="selected.slot && reweaver"
                            type="button"
                            class="pixel-button pixel-button-muted"
                            :disabled="busy"
                            @click="rerollBonuses"
                        >
                            Boni neu rollen
                        </button>
                        <button
                            v-if="selected.slot && prism && selected.bonuses.length >= 4"
                            type="button"
                            class="pixel-button pixel-button-muted"
                            :disabled="busy"
                            @click="unlockBonus"
                        >
                            5. Bonus freischalten
                        </button>
                        <div v-if="selected.column !== null && selected.row !== null" class="grid grid-cols-[1fr_1fr_auto] gap-1.5">
                            <label class="text-[0.55rem] uppercase tracking-widest text-stone-500">
                                Spalte
                                <input v-model.number="moveColumn" type="number" min="0" max="4" class="mt-1 w-full rounded border border-amber-500/30 bg-black/30 px-2 py-1.5 text-xs text-stone-200" />
                            </label>
                            <label class="text-[0.55rem] uppercase tracking-widest text-stone-500">
                                Reihe
                                <input v-model.number="moveRow" type="number" min="0" max="8" class="mt-1 w-full rounded border border-amber-500/30 bg-black/30 px-2 py-1.5 text-xs text-stone-200" />
                            </label>
                            <button type="button" class="pixel-button pixel-button-muted self-end px-2 text-[0.55rem]" :disabled="busy" @click="moveItem">
                                Move
                            </button>
                        </div>
                    </div>
                </template>
                <p v-else class="text-sm text-stone-400">Wähle ein Item aus der Tasche.</p>
            </aside>
        </div>
    </div>
</template>
