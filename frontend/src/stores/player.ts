import { computed, ref } from 'vue';
import { defineStore } from 'pinia';

import { ApiError } from '@/api/client';
import { fetchState } from '@/api/game';
import type { GameStatePayload, PlayerSnapshot } from '@/types/game';

export const usePlayerStore = defineStore('player', () => {
    const state = ref<GameStatePayload | null>(null);
    const loading = ref(false);
    const error = ref<string | null>(null);
    const requiresLogin = ref(false);

    const snapshot = computed<PlayerSnapshot | null>(() => state.value?.player ?? null);
    const name = computed(() => snapshot.value?.name ?? 'Wanderer');
    const level = computed(() => snapshot.value?.level ?? 1);
    const healthPercent = computed(() => {
        if (!snapshot.value) {
            return 0;
        }

        return Math.round((snapshot.value.health / snapshot.value.maxHealth) * 100);
    });
    const staminaPercent = computed(() => {
        if (!snapshot.value) {
            return 0;
        }

        return Math.round((snapshot.value.stamina / snapshot.value.maxStamina) * 100);
    });
    const spPercent = computed(() => {
        if (!snapshot.value) {
            return 0;
        }

        return Math.round((snapshot.value.sp / snapshot.value.maxSp) * 100);
    });

    async function load(force = false): Promise<void> {
        if ((state.value !== null && !force) || loading.value) {
            return;
        }

        loading.value = true;
        error.value = null;
        requiresLogin.value = false;

        try {
            state.value = await fetchState();
        } catch (caught) {
            if (caught instanceof ApiError && caught.status === 401) {
                requiresLogin.value = true;
            } else {
                error.value = caught instanceof Error ? caught.message : 'Spielerdaten konnten nicht geladen werden.';
            }
        } finally {
            loading.value = false;
        }
    }

    function applyState(next: GameStatePayload): void {
        state.value = next;
    }

    return {
        state,
        snapshot,
        loading,
        error,
        requiresLogin,
        name,
        level,
        healthPercent,
        staminaPercent,
        spPercent,
        load,
        applyState,
    };
});
