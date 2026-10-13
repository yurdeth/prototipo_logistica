<script setup lang="ts">
import { ChevronDown, ChevronUp } from '@/UI/Icons';
import type { Columna } from './Utils';

// Estructura de la tabla (encabezado y mensaje vacío). Las filas se pasan por el slot
withDefaults(
    defineProps<{
        columns: Columna[];
        sort?: { key: string; dir: string };
        vacio?: boolean;
        emptyTitle?: string;
        emptyHint?: string;
        variant?: 'default' | 'dashboard';
    }>(),
    {
        emptyTitle: 'No se encontraron resultados',
        emptyHint: 'Prueba modificar o eliminar alguno de los filtros.',
        variant: 'default',
    },
);
defineEmits<{ sort: [key: string] }>();
</script>

<template>
    <div class="overflow-x-auto">
        <table
            class="w-full text-left text-sm"
            :class="variant === 'dashboard' ? 'min-w-[780px]' : ''"
        >
            <thead
                :class="variant === 'dashboard' ? 'bg-muted/30' : 'bg-muted/40'"
            >
                <tr
                    class="border-b border-sidebar-border/70 text-xs tracking-wide text-muted-foreground uppercase"
                >
                    <th
                        v-for="c in columns"
                        :key="c.label"
                        class="px-5 py-3.5 font-semibold"
                        :class="[
                            c.clase,
                            variant === 'dashboard'
                                ? 'py-4 text-[11px] tracking-[0.12em]'
                                : '',
                        ]"
                    >
                        <!-- Las columnas con `key` se pueden ordenar. -->
                        <button
                            v-if="c.key"
                            type="button"
                            class="inline-flex items-center gap-1.5 hover:text-foreground"
                            @click="$emit('sort', c.key)"
                        >
                            {{ c.label }}
                            <ChevronUp
                                v-if="sort?.key === c.key && sort.dir === 'asc'"
                                class="h-3.5 w-3.5"
                            />
                            <ChevronDown
                                v-else-if="sort?.key === c.key"
                                class="h-3.5 w-3.5"
                            />
                        </button>
                        <template v-else>{{ c.label }}</template>
                    </th>
                </tr>
            </thead>

            <tbody>
                <slot />

                <!-- Mensaje cuando no hay resultados para los filtros aplicados. -->
                <tr v-if="vacio">
                    <td
                        :colspan="columns.length"
                        class="px-5 py-14 text-center text-sm text-muted-foreground"
                    >
                        <p class="font-medium text-foreground">
                            {{ emptyTitle }}
                        </p>
                        <p class="mt-1">{{ emptyHint }}</p>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
