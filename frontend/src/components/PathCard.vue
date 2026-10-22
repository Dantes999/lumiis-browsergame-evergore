<script setup lang="ts">
import { computed } from 'vue';

import PixelAvatar from '@/components/PixelAvatar.vue';
import type { RegionPath } from '@/types/game';

const props = defineProps<{
    path: RegionPath;
    busy?: boolean;
}>();

const emit = defineEmits<{
    enter: [path: RegionPath];
}>();

const difficultyLabel = computed(() => {
    switch (props.path.difficulty) {
        case 'safe':
            return 'Sicher';
        case 'medium':
            return 'Mittel';
        default:
            return 'Schwer';
    }
});

const difficultyClass = computed(() => {
    switch (props.path.difficulty) {
        case 'safe':
            return 'border-emerald-300/30 bg-emerald-950/20 text-emerald-100';
        case 'medium':
            return 'border-amber-300/30 bg-amber-950/20 text-amber-100';
        default:
            return 'border-red-300/30 bg-red-950/20 text-red-100';
    }
});
</script>

<template>
    <article class="pixel-panel flex flex-col gap-3 p-4">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="hud-stat-label">Pfad</p>
                <h3 class="font-display mt-1 text-xl text-amber-100">{{ path.name }}</h3>
            </div>
            <span class="rounded-full border px-2.5 py-1 text-[0.62rem] font-bold uppercase tracking-widest" :class="difficultyClass">
                {{ difficultyLabel }}
            </span>
        </div>

        <p class="text-sm leading-6 text-stone-400">{{ path.description }}</p>

        <div class="flex flex-wrap gap-2">
            <div
                v-for="monster in path.monsters"
                :key="`${path.id}-${monster.slug}`"
                class="flex items-center gap-2 rounded border border-amber-500/15 bg-black/20 px-2 py-1.5"
            >
                <PixelAvatar :name="monster.name" :accent="monster.accent" :category="monster.category" size="sm" />
                <div>
                    <p class="text-xs font-semibold text-stone-200">{{ monster.name }}</p>
                    <p class="text-[0.58rem] uppercase tracking-widest text-stone-500">Lv {{ monster.level }}</p>
                </div>
            </div>
        </div>

        <dl class="grid grid-cols-3 gap-2 text-center text-xs">
            <div class="rounded border border-amber-500/15 bg-black/15 p-2">
                <dt class="hud-stat-label">Lumiis</dt>
                <dd class="mt-1 font-display text-base text-teal-200">{{ path.lumiisChance }}%</dd>
            </div>
            <div class="rounded border border-amber-500/15 bg-black/15 p-2">
                <dt class="hud-stat-label">Beute</dt>
                <dd class="mt-1 font-display text-base text-amber-200">x{{ path.lootModifier }}</dd>
            </div>
            <div class="rounded border border-amber-500/15 bg-black/15 p-2">
                <dt class="hud-stat-label">Druck</dt>
                <dd class="mt-1 font-display text-base text-orange-200">+{{ path.pressureGain }}</dd>
            </div>
        </dl>

        <button type="button" class="pixel-button mt-auto w-full" :disabled="busy" @click="emit('enter', path)">
            {{ busy ? 'Kampf wird vorbereitet …' : 'Pfad betreten' }}
        </button>
    </article>
</template>
