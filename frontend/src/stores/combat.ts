import { computed, ref } from 'vue';
import { defineStore } from 'pinia';

import { mapBattleToCombat, startEncounter, submitCombatAction } from '@/api/game';
import type { BattleSnapshot, CombatSnapshot, RegionPath } from '@/types/game';

export const useCombatStore = defineStore('combat', () => {
    const combat = ref<CombatSnapshot | null>(null);
    const loading = ref(false);
    const error = ref<string | null>(null);

    const aliveEnemies = computed(() => combat.value?.enemies.filter((enemy) => enemy.health > 0) ?? []);
    const playerHealthPercent = computed(() => {
        if (!combat.value) {
            return 0;
        }

        return Math.round((combat.value.player.health / combat.value.player.maxHealth) * 100);
    });

    async function begin(
        areaId: number,
        encounter: RegionPath['encounter'],
        regionSlug: string,
        pathId: string,
    ): Promise<void> {
        loading.value = true;
        error.value = null;

        try {
            combat.value = await startEncounter(areaId, encounter, regionSlug, pathId);
        } catch (caught) {
            error.value = caught instanceof Error ? caught.message : 'Kampf konnte nicht gestartet werden.';
        } finally {
            loading.value = false;
        }
    }

    async function act(action: 'attack' | 'skill', skillSlug?: string): Promise<void> {
        if (!combat.value || combat.value.finished || loading.value) {
            return;
        }

        loading.value = true;

        try {
            combat.value = await submitCombatAction(combat.value, action, skillSlug);
        } catch (caught) {
            error.value = caught instanceof Error ? caught.message : 'Aktion konnte nicht ausgeführt werden.';
        } finally {
            loading.value = false;
        }
    }

    function reset(): void {
        combat.value = null;
        error.value = null;
    }

    function hydrate(battle: BattleSnapshot, regionSlug = '', pathId = ''): void {
        combat.value = mapBattleToCombat(battle, regionSlug, pathId);
    }

    return {
        combat,
        loading,
        error,
        aliveEnemies,
        playerHealthPercent,
        begin,
        act,
        hydrate,
        reset,
    };
});
