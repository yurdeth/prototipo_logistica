<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { create as crearRuta, edit as editarRuta } from '@/routes/empleados';
import { dashboard } from '@/routes';
import { colors } from '@/UI/Colors';
import { empleadosDemo, type Empleado } from '@/config/Personal/empleados';
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
            { title: 'Empleados', href: '#' },
        ],
    },
});

// El personal llega como prop desde el controlador; demo solo como respaldo.
const props = withDefaults(defineProps<{ empleados?: Empleado[] }>(), {
    empleados: () => empleadosDemo,
});

const personal = computed<Empleado[]>(() => props.empleados);

/* ───────── Filtros ───────── */
const busqueda = ref('');
const estadoFiltro = ref<string | null>(null);

const periodos = [{ value: 0, label: 'Todo el historial' }] as const;

const filtrados = computed(() => {
    const q = busqueda.value.trim().toLowerCase();

    return personal.value.filter((e) => {
        const estado = e.activo ? 'ACTIVO' : 'INACTIVO';
        const coincideEstado =
            !estadoFiltro.value || estado === estadoFiltro.value;
        const coincideBusqueda =
            !q ||
            `${e.nombres} ${e.apellidos}`.toLowerCase().includes(q) ||
            e.documento_identidad.toLowerCase().includes(q) ||
            e.puesto.nombre_puesto.toLowerCase().includes(q) ||
            e.sucursal.nombre_sucursal.toLowerCase().includes(q);

        return coincideEstado && coincideBusqueda;
    });
});

const conteoPorEstado = (estado: string) =>
    personal.value.filter((e) => (e.activo ? 'ACTIVO' : 'INACTIVO') === estado)
        .length;

const estadosPresentes = computed<Chip[]>(() => [
    {
        key: 'ACTIVO',
        label: 'Activos',
        count: conteoPorEstado('ACTIVO'),
        color: colors.verde,
    },
    {
        key: 'INACTIVO',
        label: 'Inactivos',
        count: conteoPorEstado('INACTIVO'),
        color: colors.grisMedio,
    },
]);

const alEstado = (k: string) =>
    (estadoFiltro.value = estadoFiltro.value === k ? null : k);

const activos = computed<Activo[]>(() =>
    estadoFiltro.value
        ? [
              {
                  key: 'estado',
                  label:
                      estadoFiltro.value === 'ACTIVO'
                          ? 'Estado: activos'
                          : 'Estado: inactivos',
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
        label: 'Personal total',
        value: personal.value.length,
        hint: `${conteoPorEstado('ACTIVO')} activos`,
    },
    {
        label: 'Con licencia',
        value: personal.value.filter((e) => e.numero_licencia).length,
        hint: 'Habilitados para conducir despachos',
    },
    {
        label: 'Inactivos',
        value: conteoPorEstado('INACTIVO'),
        hint: 'Sin asignación operativa actualmente',
        color: conteoPorEstado('INACTIVO') > 0 ? colors.rojo : undefined,
    },
]);

/* ───────── Tabla ───────── */
const columnas: Columna[] = [
    { label: 'Empleado' },
    { label: 'Documento' },
    { label: 'Puesto' },
    { label: 'Sucursal' },
    { label: 'Estado' },
];
</script>

<template>
    <Head title="Empleados" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
        <!-- Encabezado -->
        <EncabezadoAcciones
            title="Empleados"
            subtitle="Personal de la empresa: puestos, sucursales y disponibilidad"
            accion="Nuevo empleado"
            :href="crearRuta().url"
        />

        <!-- Sección de resumen con indicadores clave del personal. -->
        <StatCards :items="resumen" :cols="3" />

        <!-- Sección principal para consultar y administrar el personal. -->
        <section
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm"
            aria-label="Listado de empleados"
        >
            <BiFilters
                v-model:busqueda="busqueda"
                :periodos="periodos"
                placeholder="Buscar por nombre, documento o puesto"
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
                empty-title="No se encontraron empleados"
            >
                <BiTableRow
                    v-for="e in filtrados"
                    :key="e.id_empleado"
                    @select="
                        router.visit(editarRuta({ empleado: e.id_empleado }))
                    "
                >
                    <!-- Nombre completo del empleado. -->
                    <td class="px-5 py-4">
                        <p class="text-sm font-medium text-foreground">
                            {{ e.nombres }} {{ e.apellidos }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Ingreso
                            {{ e.fecha_contratacion ?? '—' }}
                        </p>
                    </td>

                    <!-- Documento de identidad (único). -->
                    <td
                        class="px-5 py-4 font-mono text-sm text-muted-foreground"
                    >
                        {{ e.documento_identidad }}
                    </td>

                    <!-- Puesto y departamento al que pertenece. -->
                    <td class="px-5 py-4 text-foreground">
                        {{ e.puesto.nombre_puesto }}
                        <span class="block text-xs text-muted-foreground">
                            {{ e.puesto.departamento.nombre_departamento }}
                        </span>
                    </td>

                    <!-- Sucursal donde labora. -->
                    <td class="px-5 py-4 text-muted-foreground">
                        {{ e.sucursal.nombre_sucursal }}
                    </td>

                    <!-- Estado activo/inactivo con indicador de color. -->
                    <td class="px-5 py-4">
                        <BiStatus
                            class="text-foreground"
                            :color="e.activo ? colors.verde : colors.grisMedio"
                            :label="e.activo ? 'Activo' : 'Inactivo'"
                        />
                    </td>
                </BiTableRow>
            </BiTable>
        </section>
    </div>
</template>
