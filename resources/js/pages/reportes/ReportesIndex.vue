<script setup lang="ts">
import { computed, reactive, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { colors } from '@/UI/Colors';
import { dashboard } from '@/routes';

import {
    estadoInfo,
    prioridadInfo,
    type Prioridad,
} from '@/config/Envios/envios';
import { estadoDespachoInfo } from '@/config/Rutas/rutas';
import { fmtFecha } from '@/composables/useEnviosBI';

import BiHeader from '@/components/bi/BiHeader.vue';
import StatCards from '@/components/bi/StatCards.vue';
import BiChips from '@/components/bi/StatusChips.vue';
import BiTable from '@/components/bi/BiTable.vue';
import BiTableRow from '@/components/bi/BiTableRow.vue';
import BiPagination from '@/components/bi/BiPagination.vue';
import BiTabs from '@/components/bi/BiTabs.vue';
import BiPanelHead from '@/components/bi/BiPanelHead.vue';
import BiBarList from '@/components/bi/BiBarList.vue';
import BiBadge from '@/components/bi/BiBadge.vue';
import BiStatus from '@/components/bi/BiStatus.vue';

import type { BarRow, Chip, Columna, Stat } from '@/components/bi/Utils';

/* ───────── Tipos de lo que envía ReporteController@index ───────── */
type Plazo = 'vencido' | 'vence_pronto' | 'en_plazo';

interface FilaEnvio {
    id_envio: number;
    codigo_guia: string;
    destino: string;
    estado: keyof typeof estadoInfo;
    prioridad: Prioridad;
    fecha_comprometida: string;
    plazo: Plazo;
}

interface Paginado<T> {
    data: T[];
    pagina: number;
    ultima: number;
    total: number;
}

interface DespachoPlazo {
    id_despacho: number;
    codigo: string;
    fecha: string;
    estado: keyof typeof estadoDespachoInfo;
    sucursal: string;
    total: number;
    vencidos: number;
    vence_pronto: number;
    en_plazo: number;
}

interface FilaIncidencia {
    id: number;
    tipo: string;
    despacho: string | null;
    guia: string | null;
    minutos_retraso: number;
    descripcion: string | null;
    ocurrio_en: string;
}

interface TipoResumen {
    id: number;
    tipo: string;
    total: number;
    minutos: number;
}

const props = defineProps<{
    kpis: {
        vencidos: number;
        vence_pronto: number;
        en_plazo: number;
        incidencias: number;
        minutos_retraso: number;
    };
    envios: Paginado<FilaEnvio>;
    despachos: DespachoPlazo[];
    incidencias: Paginado<FilaIncidencia> & { por_tipo: TipoResumen[] };
    tipos: { id: number; nombre: string }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Reportes', href: '#' },
        ],
    },
});

/* ───────── Consulta al servidor ───────── */
/* Cada pestaña tiene sus propios filtros: el plazo de envíos no se arrastra
   a despachos ni incidencias, y tipo y período son exclusivos de incidencias.
   Cada consulta recarga solo los datos de su pestaña. */
type TabReporte = 'envios' | 'despachos' | 'incidencias';

const filtros = reactive({
    plazoEnvios: null as Plazo | null,
    plazoDespachos: null as Plazo | null,
    tipo: null as number | null,
    dias: 0,
});

const parametros = (tab: TabReporte): Record<string, string | number> => {
    const params: Record<string, string | number> = {};

    if (tab === 'envios' && filtros.plazoEnvios) {
        params.plazo = filtros.plazoEnvios;
    }
    if (tab === 'despachos' && filtros.plazoDespachos) {
        params.plazo = filtros.plazoDespachos;
    }
    if (tab === 'incidencias') {
        if (filtros.tipo) params.tipo = filtros.tipo;
        if (filtros.dias > 0) params.dias = filtros.dias;
    }

    return params;
};

const soloPorTab: Record<TabReporte, string[]> = {
    envios: ['kpis', 'envios'],
    despachos: ['kpis', 'despachos'],
    incidencias: ['kpis', 'incidencias'],
};

const consultar = (pagina = 1) => {
    const params = parametros(tab.value);
    if (pagina > 1) params.page = pagina;

    router.get('/reportes', params, {
        only: soloPorTab[tab.value],
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

// Descarga un CSV con las tres secciones del reporte bajo los filtros vigentes.
// Exporta únicamente la sección de la pestaña activa, con sus filtros.
const exportar = () => {
    const params = { ...parametros(tab.value), tab: tab.value };
    const qs = new URLSearchParams(
        Object.entries(params).map(([k, v]) => [k, String(v)]),
    ).toString();

    window.location.href = `/reportes/exportar${qs ? `?${qs}` : ''}`;
};

// Descarga el reporte en PDF horizontal, con los filtros aplicados en pantalla.
// Genera el PDF de la sección de la pestaña activa, con sus filtros.
const etiquetaTab = computed(
    () =>
        tabs.find((t) => t.key === tab.value)?.label.toLowerCase() ?? tab.value,
);

const exportarPdf = () => {
    const params = { ...parametros(tab.value), tab: tab.value };
    const qs = new URLSearchParams(
        Object.entries(params).map(([k, v]) => [k, String(v)]),
    ).toString();

    window.location.href = `/reportes/pdf${qs ? `?${qs}` : ''}`;
};

const plazoInfo: Record<Plazo, { label: string; color: string }> = {
    vencido: { label: 'Vencido', color: colors.rojo },
    vence_pronto: { label: 'Vence pronto', color: colors.amarillo },
    en_plazo: { label: 'En plazo', color: colors.verde },
};

const chips = computed<Chip[]>(() => [
    {
        key: 'vencido',
        label: 'Vencidos',
        count: props.kpis.vencidos,
        color: colors.rojo,
    },
    {
        key: 'vence_pronto',
        label: 'Vencen pronto',
        count: props.kpis.vence_pronto,
        color: colors.amarillo,
    },
    {
        key: 'en_plazo',
        label: 'En plazo',
        count: props.kpis.en_plazo,
        color: colors.verde,
    },
]);

const alPlazo = (clave: 'plazoEnvios' | 'plazoDespachos', k: string) => {
    filtros[clave] = filtros[clave] === k ? null : (k as Plazo);
    consultar();
};

// Sirve al select del encabezado (Evento) y al clic en el listado por tipo (key)
const alTipo = (origen: string | Event) => {
    const id =
        typeof origen === 'string'
            ? Number(origen)
            : Number((origen.target as HTMLSelectElement).value);

    filtros.tipo = filtros.tipo === id ? null : id;
    consultar();
};

const alDias = (e: Event) => {
    filtros.dias = Number((e.target as HTMLSelectElement).value);
    consultar();
};

const campoClase =
    'h-10 rounded-lg border border-sidebar-border/70 bg-card px-3 text-sm text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';

/* ───────── Tarjetas de resumen ───────── */
const tarjetas = computed<Stat[]>(() => [
    {
        label: 'Envíos vencidos',
        value: props.kpis.vencidos,
        hint: 'Compromiso ya incumplido',
        color: props.kpis.vencidos > 0 ? colors.rojo : undefined,
    },
    {
        label: 'Vencen en 48 horas',
        value: props.kpis.vence_pronto,
        hint: 'Requieren despacho urgente',
        color: props.kpis.vence_pronto > 0 ? colors.amarillo : undefined,
    },
    {
        label: 'En plazo',
        value: props.kpis.en_plazo,
        hint: 'Con margen sobre el compromiso',
    },
    {
        label: 'Incidencias',
        value: props.kpis.incidencias,
        hint: `${props.kpis.minutos_retraso.toLocaleString('es-SV')} min de retraso acumulado`,
    },
]);

/* ───────── Pestañas y tablas ───────── */
const tab = ref<TabReporte>('envios');
const tabs = [
    { key: 'envios', label: 'Envíos por vencer' },
    { key: 'despachos', label: 'Despachos por plazo' },
    { key: 'incidencias', label: 'Incidencias' },
] as const;

const columnasEnvios: Columna[] = [
    { label: 'Envío' },
    { label: 'Destino' },
    { label: 'Estado' },
    { label: 'Compromiso' },
    { label: 'Situación' },
];

const columnasDespachos: Columna[] = [
    { label: 'Despacho' },
    { label: 'Estado' },
    { label: 'Paradas' },
    { label: 'Vencidas' },
    { label: 'Por vencer' },
    { label: 'En plazo' },
];

const columnasIncidencias: Columna[] = [
    { label: 'Tipo' },
    { label: 'Despacho' },
    { label: 'Envío' },
    { label: 'Retraso' },
    { label: 'Fecha' },
    { label: 'Descripción' },
];

const periodos = [
    { value: 7, label: 'Últimos 7 días' },
    { value: 30, label: 'Últimos 30 días' },
    { value: 0, label: 'Todo el historial' },
] as const;

const paginaEnvios = computed({
    get: () => props.envios.pagina,
    set: (n: number) => consultar(n),
});

const paginaIncidencias = computed({
    get: () => props.incidencias.pagina,
    set: (n: number) => consultar(n),
});

const filasPorTipo = computed<BarRow[]>(() =>
    props.incidencias.por_tipo.map((t) => ({
        key: String(t.id),
        label: t.tipo.toLowerCase().replace(/_/g, ' '),
        detail: `${t.total} incidencia${t.total === 1 ? '' : 's'}`,
        badge:
            t.minutos > 0
                ? `${t.minutos.toLocaleString('es-SV')} min`
                : undefined,
        badgeColor: colors.rojo,
    })),
);
</script>

<template>
    <Head title="Reportes" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
        <BiHeader
            title="Reportes operativos"
            subtitle="Envíos por vencer, despachos según su compromiso e incidencias"
            accion="Exportar"
            @exportar="exportar"
        >
            <template #acciones>
                <button
                    type="button"
                    class="inline-flex h-10 items-center gap-2 rounded-lg bg-blue-700 px-4 text-sm font-medium text-white transition hover:bg-blue-800"
                    @click="exportarPdf"
                >
                    PDF de {{ etiquetaTab }}
                </button>
            </template>
        </BiHeader>

        <StatCards :items="tarjetas" :cols="4" />

        <section
            class="rounded-xl border border-sidebar-border/70 bg-card p-4 shadow-sm sm:p-5"
            aria-label="Reportes"
        >
            <BiTabs v-model="tab" :tabs="tabs">
                <div class="flex flex-wrap items-center gap-3">
                    <select
                        aria-label="Tipo de incidencia"
                        :class="[campoClase, 'w-full sm:w-auto']"
                        :value="filtros.tipo ?? ''"
                        @change="alTipo"
                    >
                        <option value="">Todos los tipos</option>
                        <option v-for="t in tipos" :key="t.id" :value="t.id">
                            {{ t.nombre.toLowerCase().replace(/_/g, ' ') }}
                        </option>
                    </select>

                    <select
                        aria-label="Período de incidencias"
                        :class="[campoClase, 'w-full sm:w-auto']"
                        :value="filtros.dias"
                        @change="alDias"
                    >
                        <option
                            v-for="p in periodos"
                            :key="p.value"
                            :value="p.value"
                        >
                            {{ p.label }}
                        </option>
                    </select>
                </div>
            </BiTabs>

            <!-- Envíos por vencer -->
            <div v-if="tab === 'envios'" class="pt-5">
                <BiChips
                    :items="chips"
                    :active="filtros.plazoEnvios"
                    label="Filtrar por situación de plazo"
                    @toggle="(k) => alPlazo('plazoEnvios', k)"
                />

                <div class="pt-4">
                    <BiTable
                        :columns="columnasEnvios"
                        :vacio="!envios.data.length"
                        empty-title="Sin envíos en esta situación"
                    >
                        <BiTableRow
                            v-for="e in envios.data"
                            :key="e.id_envio"
                            :selectable="false"
                        >
                            <td class="px-5 py-4">
                                <p
                                    class="font-mono text-sm font-medium text-foreground"
                                >
                                    {{ e.codigo_guia }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    Prioridad
                                    {{
                                        prioridadInfo[
                                            e.prioridad
                                        ].label.toLowerCase()
                                    }}
                                </p>
                            </td>

                            <td class="px-5 py-4 text-foreground">
                                {{ e.destino }}
                            </td>

                            <td class="px-5 py-4">
                                <BiStatus
                                    class="text-foreground"
                                    :color="estadoInfo[e.estado].color"
                                    :label="estadoInfo[e.estado].label"
                                />
                            </td>

                            <td
                                class="px-5 py-4 whitespace-nowrap text-muted-foreground"
                            >
                                {{ fmtFecha(e.fecha_comprometida) }}
                            </td>

                            <td class="px-5 py-4">
                                <BiBadge
                                    :color="plazoInfo[e.plazo].color"
                                    :label="plazoInfo[e.plazo].label"
                                />
                            </td>
                        </BiTableRow>
                    </BiTable>

                    <BiPagination
                        v-model="paginaEnvios"
                        :total="Math.max(1, envios.ultima)"
                        :count="envios.total"
                        unit="envíos"
                    />
                </div>
            </div>

            <!-- Despachos por plazo -->
            <div v-else-if="tab === 'despachos'" class="pt-5">
                <BiChips
                    :items="chips"
                    :active="filtros.plazoDespachos"
                    label="Filtrar despachos por situación"
                    @toggle="(k) => alPlazo('plazoDespachos', k)"
                />

                <div class="pt-4">
                    <BiTable
                        :columns="columnasDespachos"
                        :vacio="!despachos.length"
                        empty-title="Sin despachos sin cerrar en esta situación"
                    >
                        <BiTableRow
                            v-for="d in despachos"
                            :key="d.id_despacho"
                            :selectable="false"
                        >
                            <td class="px-5 py-4">
                                <p
                                    class="font-mono text-sm font-medium text-foreground"
                                >
                                    {{ d.codigo }}
                                </p>
                                <p class="mt-1 text-xs text-muted-foreground">
                                    {{ fmtFecha(d.fecha) }} ·
                                    {{ d.sucursal }}
                                </p>
                            </td>

                            <td class="px-5 py-4">
                                <BiStatus
                                    class="text-foreground"
                                    :color="estadoDespachoInfo[d.estado].color"
                                    :label="estadoDespachoInfo[d.estado].label"
                                />
                            </td>

                            <td class="px-5 py-4 text-foreground">
                                {{ d.total }}
                            </td>

                            <td class="px-5 py-4">
                                <span
                                    class="text-sm font-medium"
                                    :class="
                                        d.vencidos > 0
                                            ? 'text-red-600'
                                            : 'text-muted-foreground'
                                    "
                                    >{{ d.vencidos }}</span
                                >
                            </td>

                            <td class="px-5 py-4">
                                <span
                                    class="text-sm font-medium"
                                    :class="
                                        d.vence_pronto > 0
                                            ? 'text-amber-600'
                                            : 'text-muted-foreground'
                                    "
                                    >{{ d.vence_pronto }}</span
                                >
                            </td>

                            <td class="px-5 py-4 text-sm text-muted-foreground">
                                {{ d.en_plazo }}
                            </td>
                        </BiTableRow>
                    </BiTable>
                </div>
            </div>

            <!-- Incidencias -->
            <div v-else class="pt-5">
                <BiPanelHead
                    title="Impacto por tipo de incidencia"
                    description="Selecciona un tipo para filtrar el detalle de abajo."
                />
                <BiBarList
                    :rows="filasPorTipo"
                    :active="filtros.tipo ? String(filtros.tipo) : null"
                    empty="Sin incidencias en el período seleccionado."
                    @select="alTipo"
                />

                <div class="pt-5">
                    <BiTable
                        :columns="columnasIncidencias"
                        :vacio="!incidencias.data.length"
                        empty-title="Sin incidencias en el período seleccionado"
                    >
                        <BiTableRow
                            v-for="i in incidencias.data"
                            :key="i.id"
                            :selectable="false"
                        >
                            <td class="px-5 py-4 text-foreground">
                                {{ i.tipo.toLowerCase().replace(/_/g, ' ') }}
                            </td>

                            <td
                                class="px-5 py-4 font-mono text-xs text-muted-foreground"
                            >
                                {{ i.despacho ?? '—' }}
                            </td>

                            <td
                                class="px-5 py-4 font-mono text-xs text-muted-foreground"
                            >
                                {{ i.guia ?? '—' }}
                            </td>

                            <td class="px-5 py-4">
                                <span
                                    class="text-sm font-medium"
                                    :class="
                                        i.minutos_retraso > 0
                                            ? 'text-red-600'
                                            : 'text-muted-foreground'
                                    "
                                    >{{ i.minutos_retraso }} min</span
                                >
                            </td>

                            <td
                                class="px-5 py-4 whitespace-nowrap text-muted-foreground"
                            >
                                {{ fmtFecha(i.ocurrio_en) }}
                            </td>

                            <td class="max-w-xs px-5 py-4">
                                <p
                                    class="truncate text-sm text-muted-foreground"
                                    :title="i.descripcion ?? ''"
                                >
                                    {{ i.descripcion ?? '—' }}
                                </p>
                            </td>
                        </BiTableRow>
                    </BiTable>

                    <BiPagination
                        v-model="paginaIncidencias"
                        :total="Math.max(1, incidencias.ultima)"
                        :count="incidencias.total"
                        unit="incidencias"
                    />
                </div>
            </div>
        </section>
    </div>
</template>
