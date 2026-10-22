import { ref } from 'vue';
import { defineStore } from 'pinia';

export type GameWindow = 'character' | 'inventory' | 'quests' | 'chat' | null;

export const useUiStore = defineStore('ui', () => {
    const activeWindow = ref<GameWindow>(null);
    const npcDialog = ref<{ name: string; role: string; text: string } | null>(null);

    function openWindow(window: Exclude<GameWindow, null>): void {
        activeWindow.value = window;
    }

    function closeWindow(): void {
        activeWindow.value = null;
    }

    function openNpcDialog(dialog: { name: string; role: string; text: string }): void {
        npcDialog.value = dialog;
    }

    function closeNpcDialog(): void {
        npcDialog.value = null;
    }

    return {
        activeWindow,
        npcDialog,
        openWindow,
        closeWindow,
        openNpcDialog,
        closeNpcDialog,
    };
});
