<script setup lang="ts">
import { ChevronLeft, ChevronRight } from '@/UI/Icons';

const page = defineModel<number>({ required: true });
withDefaults(defineProps<{ total: number; count: number; unit?: string }>(), {
    unit: 'resultados',
});

const btn =
    'rounded-lg border border-sidebar-border/70 bg-card p-2 text-foreground transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-40';
</script>

<template>
    <!-- Controles para navegar entre las páginas del listado. -->
    <div
        class="flex flex-col gap-3 border-t border-sidebar-border/70 bg-muted/20 px-5 py-4 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between"
    >
        <span>{{ count }} {{ unit }} · Página {{ page }} de {{ total }}</span>

        <div class="flex items-center gap-2">
            <button
                type="button"
                :class="btn"
                :disabled="page <= 1"
                aria-label="Página anterior"
                @click="page--"
            >
                <ChevronLeft class="h-4 w-4" />
            </button>
            <button
                type="button"
                :class="btn"
                :disabled="page >= total"
                aria-label="Página siguiente"
                @click="page++"
            >
                <ChevronRight class="h-4 w-4" />
            </button>
        </div>
    </div>
</template>
