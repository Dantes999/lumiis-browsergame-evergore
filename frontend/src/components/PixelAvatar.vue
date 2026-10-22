<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        name: string;
        accent?: string;
        category?: 'monster' | 'lumiis' | 'boss' | 'npc';
        size?: 'sm' | 'md' | 'lg' | 'xl';
    }>(),
    {
        accent: '#9a7650',
        category: 'monster',
        size: 'md',
    },
);

const initials = computed(() =>
    props.name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase() ?? '')
        .join(''),
);

const sizeClass = computed(() => {
    switch (props.size) {
        case 'sm':
            return 'h-9 w-9 text-[0.62rem]';
        case 'lg':
            return 'h-20 w-20 text-xl';
        case 'xl':
            return 'h-28 w-28 text-3xl';
        default:
            return 'h-14 w-14 text-base';
    }
});

const categoryClass = computed(() => {
    switch (props.category) {
        case 'lumiis':
            return 'shadow-[0_0_22px_rgba(143,212,201,0.35)] border-teal-200/60';
        case 'boss':
            return 'shadow-[0_0_26px_rgba(183,75,59,0.42)] border-red-300/60';
        case 'npc':
            return 'border-amber-200/40';
        default:
            return 'border-black/35';
    }
});
</script>

<template>
    <div
        class="monster-glyph shrink-0"
        :class="[sizeClass, categoryClass]"
        :style="{ '--glyph-accent': accent }"
        :title="name"
    >
        <span>{{ initials }}</span>
    </div>
</template>
