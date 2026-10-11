<script setup lang="ts">
import type { Chip } from './Utils';

withDefaults(
    defineProps<{ items: Chip[]; active?: string | null; label?: string }>(),
    { label: 'Filtrar por estado' },
);
defineEmits<{ toggle: [key: string] }>();
</script>

<template>
    <!-- Filtros por estado con el total correspondiente. -->
    <div
        class="flex flex-wrap items-center gap-2"
        role="group"
        :aria-label="label"
    >
        <button
            v-for="c in items"
            :key="c.key"
            type="button"
            class="inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition-colors"
            :class="
                active === c.key
                    ? 'border-foreground bg-foreground text-background'
                    : 'border-sidebar-border/70 bg-card text-foreground hover:bg-muted'
            "
            :aria-pressed="active === c.key"
            @click="$emit('toggle', c.key)"
        >
            <span
                class="h-2 w-2 rounded-full"
                :style="{ backgroundColor: c.color }"
            />
            {{ c.label }}
            <span
                class="rounded-md bg-muted/70 px-1.5 py-0.5 text-xs text-muted-foreground"
                >{{ c.count }}</span
            >
        </button>
    </div>
</template>
