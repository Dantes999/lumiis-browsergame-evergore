<script setup lang="ts">
withDefaults(
    defineProps<{
        title: string;
        kicker?: string;
        wide?: boolean;
    }>(),
    {
        kicker: '',
        wide: false,
    },
);

const emit = defineEmits<{
    close: [];
}>();
</script>

<template>
    <Teleport to="body">
        <Transition name="dialog" appear>
            <div
                class="fixed inset-0 z-50 flex items-end justify-center bg-black/65 p-0 backdrop-blur-sm sm:items-center sm:p-4"
                role="dialog"
                aria-modal="true"
                :aria-label="title"
                @click.self="emit('close')"
                @keydown.esc="emit('close')"
            >
                <div
                    class="dialog-panel pixel-frame flex max-h-[92dvh] w-full flex-col overflow-hidden rounded-b-none sm:max-h-[86vh] sm:rounded-b-2xl"
                    :class="wide ? 'sm:max-w-4xl' : 'sm:max-w-2xl'"
                >
                    <header class="flex items-start justify-between gap-4 border-b border-amber-500/20 px-4 py-4 sm:px-5">
                        <div>
                            <p v-if="kicker" class="hud-stat-label">{{ kicker }}</p>
                            <h2 class="font-display mt-1 text-xl tracking-[0.08em] text-amber-100 sm:text-2xl">
                                {{ title }}
                            </h2>
                        </div>
                        <button type="button" class="pixel-button pixel-button-muted min-h-9 px-3" @click="emit('close')">
                            Schließen
                        </button>
                    </header>

                    <div class="pixel-scroll overflow-y-auto px-4 py-4 sm:px-5">
                        <slot />
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
