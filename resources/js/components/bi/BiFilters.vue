<script setup lang="ts">
import { Search, X } from '@/UI/Icons';
import type { Activo } from './Utils';

withDefaults(
    defineProps<{
        placeholder: string;
        periodos: readonly { value: number; label: string }[];
        activos: Activo[];
        hayFiltros?: boolean;
        alertaLabel?: string;
    }>(),
    { alertaLabel: 'Solo con alerta' },
);
defineEmits<{ quitar: [key: string]; limpiar: [] }>();

const busqueda = defineModel<string>('busqueda', { default: '' });
const periodo = defineModel<number>('periodo', { default: 0 });
const soloAlertas = defineModel<boolean>('soloAlertas', { default: false });

const controlClase =
    'h-10 rounded-lg border border-sidebar-border/70 bg-card px-3 text-sm text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';
</script>

<template>
    <!-- Panel de búsqueda y filtros del listado. -->
    <div
        class="flex flex-col gap-4 border-b border-sidebar-border/70 bg-muted/20 p-4 sm:p-5"
    >
        <div
            class="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center"
        >
            <div class="relative min-w-0 flex-1 sm:max-w-sm">
                <Search
                    class="absolute top-3 left-3 h-4 w-4 text-muted-foreground"
                    aria-hidden="true"
                />
                <input
                    v-model="busqueda"
                    type="search"
                    :placeholder="placeholder"
                    aria-label="Buscar"
                    :class="[controlClase, 'w-full pl-9']"
                />
            </div>

            <select
                v-model.number="periodo"
                aria-label="Período"
                :class="[controlClase, 'w-full sm:w-auto']"
            >
                <option v-for="p in periodos" :key="p.value" :value="p.value">
                    {{ p.label }}
                </option>
            </select>

            <button
                type="button"
                class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border px-4 text-sm font-medium transition-colors hover:bg-muted"
                :class="
                    soloAlertas
                        ? 'border-orange-500/50 bg-orange-500/10 text-orange-700 dark:text-orange-300'
                        : 'border-sidebar-border/70 bg-card text-foreground'
                "
                :aria-pressed="soloAlertas"
                @click="soloAlertas = !soloAlertas"
            >
                {{ alertaLabel }}
            </button>

            <!-- Controles opcionales en la misma fila (por ejemplo sucursal o fechas). -->
            <slot name="extra" />
        </div>

        <!-- Aquí van los filtros por estado (BiChips). -->
        <slot />

        <!-- Filtros activos, cada uno se puede quitar individualmente. -->
        <div
            v-if="activos.length || hayFiltros"
            class="flex flex-wrap items-center gap-2 text-sm"
        >
            <span class="font-medium text-muted-foreground"
                >Filtros activos:</span
            >

            <span
                v-for="a in activos"
                :key="a.key"
                class="inline-flex items-center gap-2 rounded-lg border border-sidebar-border/70 bg-card px-3 py-1.5 text-foreground"
            >
                {{ a.label }}
                <button
                    type="button"
                    class="rounded p-0.5 hover:bg-muted"
                    :aria-label="`Quitar filtro ${a.label}`"
                    @click="$emit('quitar', a.key)"
                >
                    <X class="h-3.5 w-3.5" />
                </button>
            </span>

            <button
                type="button"
                class="font-medium text-blue-700 hover:underline dark:text-blue-300"
                @click="$emit('limpiar')"
            >
                Limpiar filtros
            </button>
        </div>
    </div>
</template>
