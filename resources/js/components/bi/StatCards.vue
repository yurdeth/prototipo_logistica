<script setup lang="ts">
import type { Stat } from './Utils';

// Define cuántas tarjetas se mostrarán por fila.
withDefaults(
    defineProps<{
        items: Stat[];
        cols?: 3 | 4;
    }>(),
    { cols: 3 },
);
</script>

<template>
    <!-- Indicadores clave adaptables al tamaño de pantalla. -->
    <section
        aria-label="Resumen"
        class="grid grid-cols-1 gap-4 sm:grid-cols-2"
        :class="cols === 4 ? 'xl:grid-cols-4' : 'xl:grid-cols-3'"
    >
        <div
            v-for="s in items"
            :key="s.label"
            class="min-w-0 rounded-xl border border-sidebar-border/70 bg-card p-5 shadow-sm transition-shadow hover:shadow-md"
        >
            <p class="text-sm font-medium text-muted-foreground">
                {{ s.label }}
            </p>

            <p
                class="mt-2 text-3xl font-semibold tracking-tight break-words text-foreground"
                :style="{ color: s.color }"
            >
                {{ s.value }}
            </p>

            <p
                v-if="s.hint"
                class="mt-2 text-sm break-words text-muted-foreground"
            >
                {{ s.hint }}
            </p>
        </div>
    </section>
</template>
