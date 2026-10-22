<script setup lang="ts">
import { computed, ref } from 'vue';

import { runGameAction } from '@/api/game';
import { usePlayerStore } from '@/stores/player';

const player = usePlayerStore();
const tab = ref<'global' | 'whisper'>('global');
const message = ref('');
const recipient = ref('');
const busy = ref(false);
const feedback = ref<string | null>(null);

const globalMessages = computed(() => player.state?.messages.global ?? []);
const whispers = computed(() => player.state?.messages.whispers ?? []);

async function send(): Promise<void> {
    const body = message.value.trim();

    if (body === '' || busy.value) {
        return;
    }

    busy.value = true;
    feedback.value = null;

    try {
        const response = await runGameAction(tab.value === 'global' ? 'global-chat' : 'whisper', {
            message: body,
            ...(tab.value === 'whisper' ? { recipient_name: recipient.value.trim() } : {}),
        });
        feedback.value = response.message;
        message.value = '';

        if (response.state) {
            player.applyState(response.state);
        }
    } catch (caught) {
        feedback.value = caught instanceof Error ? caught.message : 'Nachricht konnte nicht gesendet werden.';
    } finally {
        busy.value = false;
    }
}
</script>

<template>
    <div class="space-y-3">
        <div class="flex gap-2">
            <button type="button" class="pixel-button min-h-9 px-3 text-[0.6rem]" :class="{ 'pixel-button-muted': tab !== 'global' }" @click="tab = 'global'">
                Reichs-Chat
            </button>
            <button type="button" class="pixel-button min-h-9 px-3 text-[0.6rem]" :class="{ 'pixel-button-muted': tab !== 'whisper' }" @click="tab = 'whisper'">
                Flüstern
            </button>
        </div>

        <div class="pixel-scroll h-64 space-y-2 overflow-y-auto rounded border border-amber-500/15 bg-black/20 p-3">
            <template v-if="tab === 'global'">
                <p v-for="entry in globalMessages" :key="entry.id" class="text-sm leading-6">
                    <span class="font-bold text-amber-200">{{ entry.sender }}</span>
                    <span class="text-stone-300">: {{ entry.body }}</span>
                </p>
                <p v-if="globalMessages.length === 0" class="text-sm text-stone-500">Noch keine Nachrichten.</p>
            </template>
            <template v-else>
                <p v-for="entry in whispers" :key="entry.id" class="text-sm leading-6">
                    <span class="text-[0.6rem] uppercase tracking-widest text-stone-500">
                        {{ entry.incoming ? 'Von' : 'An' }}
                    </span>
                    <span class="font-bold text-amber-200"> {{ entry.incoming ? entry.sender : entry.recipient }}</span>
                    <span class="text-stone-300">: {{ entry.body }}</span>
                </p>
                <p v-if="whispers.length === 0" class="text-sm text-stone-500">Noch keine Flüsternachrichten.</p>
            </template>
        </div>

        <p v-if="feedback" class="rounded border border-amber-500/25 bg-black/25 px-3 py-2 text-sm text-amber-100">
            {{ feedback }}
        </p>

        <form class="grid gap-2 sm:grid-cols-[minmax(0,1fr)_auto]" @submit.prevent="send">
            <input
                v-if="tab === 'whisper'"
                v-model="recipient"
                type="text"
                maxlength="20"
                placeholder="Empfänger"
                class="rounded border border-amber-500/30 bg-black/30 px-3 py-2 text-sm text-stone-200 outline-none focus:border-amber-300/60"
            />
            <input
                v-model="message"
                type="text"
                maxlength="280"
                placeholder="Nachricht"
                class="rounded border border-amber-500/30 bg-black/30 px-3 py-2 text-sm text-stone-200 outline-none focus:border-amber-300/60"
            />
            <button type="submit" class="pixel-button" :disabled="busy || message.trim() === ''">
                Senden
            </button>
        </form>
    </div>
</template>
