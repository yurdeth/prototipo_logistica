<script setup lang="ts">
withDefaults(
    defineProps<{
        tabs: readonly { key: string; label: string }[];
        label?: string;
    }>(),
    {
        label: 'Categorías de análisis',
    },
);
const tab = defineModel<string>({ required: true });
</script>

<template>
    <!-- Pestañas de análisis. El slot muestra un resumen a la derecha. -->
    <div
        class="flex flex-col gap-4 border-b border-sidebar-border/70 pb-4 lg:flex-row lg:items-center lg:justify-between"
        role="tablist"
        :aria-label="label"
    >
        <div class="flex flex-wrap gap-1 rounded-lg bg-muted/50 p-1">
            <button
                v-for="t in tabs"
                :key="t.key"
                type="button"
                role="tab"
                :aria-selected="tab === t.key"
                class="rounded-md px-3 py-2 text-sm transition-colors"
                :class="
                    tab === t.key
                        ? 'bg-card font-semibold text-foreground shadow-sm'
                        : 'text-muted-foreground hover:text-foreground'
                "
                @click="tab = t.key"
            >
                {{ t.label }}
            </button>
        </div>

        <slot />
    </div>
</template>
