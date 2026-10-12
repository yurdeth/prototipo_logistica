<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { create as crearRuta, edit as editarRuta } from '@/routes/vehiculos';
import { dashboard } from '@/routes';
import { colors } from '@/UI/Colors';
import {
    colorDeEstado,
    labelDeEstado,
    vehiculosDemo,
    type Vehiculo,
} from '@/config/Flota/vehiculos';
import { fmtNumero } from '@/lib/formularios';
import type { Activo, Chip, Columna, Stat } from '@/components/bi/Utils';

import EncabezadoAcciones from '@/components/bi/BiHeader.vue';
import StatCards from '@/components/bi/StatCards.vue';
import BiFilters from '@/components/bi/BiFilters.vue';
import BiChips from '@/components/bi/StatusChips.vue';
import BiTable from '@/components/bi/BiTable.vue';
import BiTableRow from '@/components/bi/BiTableRow.vue';
import BiStatus from '@/components/bi/BiStatus.vue';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Vehículos', href: '#' },
        ],
    },
});

// La flota llega como prop desde el controlador; demo solo como respaldo.
const props = withDefaults(defineProps<{ vehiculos?: Vehiculo[] }>(), {
    vehiculos: () => vehiculosDemo,
});

const flota = computed<Vehiculo[]>(() => props.vehiculos);

/* ───────── Filtros ───────── */
const busqueda = ref('');
const estadoFiltro = ref<string | null>(null);

const periodos = [{ value: 0, label: 'Todo el historial' }] as const;

const filtrados = computed(() => {
    const q = busqueda.value.trim().toLowerCase();

    return flota.value.filter((v) => {
        const coincideEstado =
            !estadoFiltro.value ||
            v.estado.nombre_estado === estadoFiltro.value;
        const coincideBusqueda =
            !q ||
            v.placa.toLowerCase().includes(q) ||
            `${v.marca ?? ''} ${v.modelo ?? ''}`.toLowerCase().includes(q) ||
            v.sucursal.nombre_sucursal.toLowerCase().includes(q);

        return coincideEstado && coincideBusqueda;
    });
});

const conteoPorEstado = (estado: string) =>
    flota.value.filter((v) => v.estado.nombre_estado === estado).length;

// Etiquetas de los estados presentes en la flota real (o la de demo).
const estadosPresentes = computed<Chip[]>(() => {
    const unicos = [...new Set(flota.value.map((v) => v.estado.nombre_estado))];

    return unicos.map((estado) => ({
        key: estado,
        label: labelDeEstado(estado),
        count: conteoPorEstado(estado),
        color: colorDeEstado(estado),
    }));
});

const alEstado = (k: string) =>
    (estadoFiltro.value = estadoFiltro.value === k ? null : k);

const activos = computed<Activo[]>(() =>
    estadoFiltro.value
        ? [
              {
                  key: 'estado',
                  label: `Estado: ${labelDeEstado(estadoFiltro.value)}`,
              },
          ]
        : [],
);

const quitarFiltro = (key: string) => {
    if (key === 'estado') estadoFiltro.value = null;
};
const limpiar = () => {
    busqueda.value = '';
    estadoFiltro.value = null;
};

/* ───────── Resumen ───────── */
const resumen = computed<Stat[]>(() => [
    {
        label: 'Flota total',
        value: flota.value.length,
        hint: `${conteoPorEstado('DISPONIBLE')} disponibles para despacho`,
    },
    {
        label: 'En ruta',
        value: conteoPorEstado('EN RUTA'),
        hint: 'Vehículos ejecutando despachos',
    },
    {
        label: 'En mantenimiento',
        value: conteoPorEstado('MANTENIMIENTO'),
        hint: 'No disponibles hasta su liberación',
        color: conteoPorEstado('MANTENIMIENTO') > 0 ? colors.rojo : undefined,
    },
]);

/* ───────── Tabla ───────── */
const columnas: Columna[] = [
    { label: 'Vehículo' },
    { label: 'Tipo' },
    { label: 'Estado' },
    { label: 'Sucursal base' },
    { label: 'Consumo' },
];
</script>

<template>
    <Head title="Vehículos" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
        <!-- Encabezado -->
        <EncabezadoAcciones
            title="Vehículos"
            subtitle="Parque de vehículos: tipos, estados y consumo de la flota"
            accion="Nuevo vehículo"
            :href="crearRuta().url"
        />

        <!-- Sección de resumen con indicadores clave de la flota. -->
        <StatCards :items="resumen" :cols="3" />

        <!-- Sección principal para consultar y administrar los vehículos. -->
        <section
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm"
            aria-label="Listado de vehículos"
        >
            <BiFilters
                v-model:busqueda="busqueda"
                :periodos="periodos"
                placeholder="Buscar por placa, marca o sucursal"
                :activos="activos"
                :hay-filtros="!!(busqueda || estadoFiltro)"
                @quitar="quitarFiltro"
                @limpiar="limpiar"
            >
                <BiChips
                    :items="estadosPresentes"
                    :active="estadoFiltro"
                    @toggle="alEstado"
                />
            </BiFilters>

            <BiTable
                :columns="columnas"
                :vacio="!filtrados.length"
                empty-title="No se encontraron vehículos"
            >
                <BiTableRow
                    v-for="v in filtrados"
                    :key="v.id_vehiculo"
                    @select="
                        router.visit(editarRuta({ vehiculo: v.id_vehiculo }))
                    "
                >
                    <!-- Identificación del vehículo. -->
                    <td class="px-5 py-4">
                        <p
                            class="font-mono text-sm font-medium text-foreground"
                        >
                            {{ v.placa }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ v.marca ?? '—' }} {{ v.modelo ?? '' }}
                            <template v-if="v.anio">· {{ v.anio }}</template>
                        </p>
                    </td>

                    <!-- Tipo con su capacidad de carga en kilogramos. -->
                    <td class="px-5 py-4 text-foreground">
                        {{ v.tipo.nombre }}
                        <span class="block text-xs text-muted-foreground">
                            {{ fmtNumero(Number(v.tipo.capacidad_kg), 0) }} kg
                        </span>
                    </td>

                    <!-- Estado actual identificado mediante un indicador de color. -->
                    <td class="px-5 py-4">
                        <BiStatus
                            class="text-foreground"
                            :color="colorDeEstado(v.estado.nombre_estado)"
                            :label="labelDeEstado(v.estado.nombre_estado)"
                        />
                    </td>

                    <!-- Sucursal base desde la que opera. -->
                    <td class="px-5 py-4 text-muted-foreground">
                        {{ v.sucursal.nombre_sucursal }}
                    </td>

                    <!-- Consumo de combustible en kilómetros por galón. -->
                    <td
                        class="px-5 py-4 whitespace-nowrap text-muted-foreground"
                    >
                        {{
                            v.consumo_km_gal
                                ? `${fmtNumero(Number(v.consumo_km_gal), 2)} km/gal`
                                : '—'
                        }}
                    </td>
                </BiTableRow>
            </BiTable>
        </section>
    </div>
</template>
