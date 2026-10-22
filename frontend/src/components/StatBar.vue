<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        label: string;
        value: number;
        max: number;
        tone?: 'health' | 'stamina' | 'sp' | 'pressure' | 'gold';
        showNumbers?: boolean;
    }>(),
    {
        tone: 'health',
        showNumbers: true,
    },
);

const percent = computed(() => {
    if (props.max <= 0) {
        return 0;
    }

    return Math.max(0, Math.min(100, Math.round((props.value / props.max) * 100)));
});

const toneClass = computed(() => {
    switch (props.tone) {
        case 'stamina':
            return 'from-amber-300 to-yellow-600';
        case 'sp':
            return 'from-sky-300 to-blue-600';
        case 'pressure':
            return 'from-orange-300 to-red-700';
        case 'gold':
            return 'from-yellow-200 to-amber-600';
        default:
            return 'from-rose-400 to-red-700';
    }
});
</script>

<template>
    <div class="min-w-0">
        <div class="flex items-baseline justify-between gap-2">
            <span class="hud-stat-label">{{ label }}</span>
            <span v-if="showNumbers" class="text-[0.62rem] font-bold tracking-wide text-stone-300">
                {{ value }}/{{ max }}
            </span>
        </div>
        <div class="mt-1 h-2 overflow-hidden rounded-full border border-black/40 bg-black/35">
            <div
                class="h-full rounded-full bg-gradient-to-r transition-[width] duration-300"
                :class="toneClass"
                :style="{ width: `${percent}%` }"
            />
        </div>
    </div>
</template>
