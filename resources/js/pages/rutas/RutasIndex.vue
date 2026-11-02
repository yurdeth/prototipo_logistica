<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref, toValue } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { colors } from '@/UI/Colors';
import { dashboard } from '@/routes';
import { MapPin } from '@/UI/Icons';
import { useClipboard } from '@vueuse/core';
import { Check, Copy } from '@lucide/vue';

import {
    estadoDespachoInfo,
    estadoParadaInfo,
    TOLERANCIA_MIN,
    UMBRAL_DESVIACION,
    VELOCIDAD_LENTA,
    type Conexion,
    type Despacho,
} from '@/config/Rutas/rutas';

import {
    useRutasBI,
    analizarConexiones,
    estadoParada,
    resumenParadas,
    retrasoMin,
    desviacionKm,
    costoPorKm,
    tieneAlerta,
    fmtHora,
    fmtFecha,
    fmtUsd,
    type SortKey,
} from '@/composables/useRutasBI';

// Componentes reutilizables.
import EncabezadoAcciones from '@/components/bi/BiHeader.vue';
import StatCards from '@/components/bi/StatCards.vue';
import BiFilters from '@/components/bi/BiFilters.vue';
import BiChips from '@/components/bi/StatusChips.vue';
import BiTable from '@/components/bi/BiTable.vue';
import BiTableRow from '@/components/bi/BiTableRow.vue';
import BiPagination from '@/components/bi/BiPagination.vue';
import BiTabs from '@/components/bi/BiTabs.vue';
import BiPanelHead from '@/components/bi/BiPanelHead.vue';
import BiLegend from '@/components/bi/BiLegend.vue';
import BiBarList from '@/components/bi/BiBarList.vue';
import BiDrawer from '@/components/bi/DetailPanel.vue';
import BiStateBox from '@/components/bi/BiStateBox.vue';
import BiFields from '@/components/bi/DetailFields.vue';
import BiTimeline from '@/components/bi/BiTimeline.vue';
import BiBadge from '@/components/bi/BiBadge.vue';
import BiStatus from '@/components/bi/BiStatus.vue';
import InputField from '@/components/Forms/InputField.vue';
import SelectField from '@/components/Forms/SelectField.vue';
import type {
    Activo,
    BarRow,
    Chip,
    Columna,
    Field,
    Stat,
    TimelineItem,
} from '@/components/bi/Utils';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Rutas', href: '#' },
        ],
    },
});

const props = defineProps<{
    despachos: Despacho[];
    conexiones: Conexion[];
    tipos_incidencia: { id: number; nombre: string }[];
    permisos: { gestionar: boolean };
}>();

// Datos, indicadores y funciones de la vista.
const {
    now,
    filtros,
    sort,
    page,
    filtrados,
    kpis,
    porEstado,
    kmPorDia,
    porConductor,
    porVehiculo,
    pagina,
    totalPaginas,
    ordenarPor,
    toggleEstado,
    toggleConductor,
    toggleVehiculo,
    hayFiltros,
    limpiar,
    exportarCsv,
} = useRutasBI(computed(() => props.despachos));

const conexionesAnalizadas = computed(() =>
    analizarConexiones(props.conexiones),
);
const velocidadMax = computed(() =>
    Math.max(1, ...conexionesAnalizadas.value.map((c) => c.velocidad)),
);

const seleccionado = ref<Despacho | null>(null);

/* ───────── Copiar código del despacho ───────── */
const { copy } = useClipboard();
const codigoCopiado = ref<string | null>(null);
let copiadoTimer: ReturnType<typeof setTimeout> | undefined;

const copiarCodigo = async (codigo: string) => {
    await copy(codigo);
    codigoCopiado.value = codigo;
    clearTimeout(copiadoTimer);
    copiadoTimer = setTimeout(() => (codigoCopiado.value = null), 2000);
};

onBeforeUnmount(() => clearTimeout(copiadoTimer));

// ── Utilidades visuales ──
const colorPct = (p: number) =>
    p >= 90 ? colors.verde : p >= 75 ? colors.amarillo : colors.rojo;
const colorDesv = (d: number) =>
    d > UMBRAL_DESVIACION
        ? colors.rojo
        : d > 5
          ? colors.amarillo
          : colors.verde;
const signo = (n: number) => `${n > 0 ? '+' : ''}${n.toFixed(1)}%`;

// Solo se colorea lo que está fuera de lo normal; lo demás queda en neutro.
const alertaPct = (p: number) => (p >= 90 ? undefined : colorPct(p));
const alertaDesv = (d: number) => (d > 5 ? colorDesv(d) : undefined);
const desv = (d: Despacho) => {
    const v = desviacionKm(d);
    return v === null ? null : { texto: signo(v), color: alertaDesv(v) };
};

// ── Tarjetas de indicadores ──
const estadisticas = computed<Stat[]>(() => {
    const k = toValue(kpis);
    return [
        {
            label: 'Despachos',
            value: k.total,
            hint: `${k.enCurso} en curso · ${k.planificados} planificados · ${k.completadosN} completados`,
        },
        {
            label: 'Puntualidad de paradas',
            value:
                k.puntualidad === null ? '—' : `${k.puntualidad.toFixed(1)}%`,
            hint: `${k.aTiempo} de ${k.evaluadas} a tiempo (±${TOLERANCIA_MIN} min)`,
            color:
                k.puntualidad === null ? undefined : alertaPct(k.puntualidad),
        },
        {
            label: 'Desviación de kilómetros',
            value: k.desviacion === null ? '—' : signo(k.desviacion),
            hint: `${k.kmExtra >= 0 ? '+' : ''}${k.kmExtra.toFixed(0)} km vs. lo planificado`,
            color: k.desviacion === null ? undefined : alertaDesv(k.desviacion),
        },
        {
            label: 'Costo estimado',
            value: fmtUsd(k.costoTotal),
            hint:
                k.costoPorKm === null
                    ? 'Sin datos de costo por km'
                    : `${fmtUsd(k.costoPorKm)} por km planificado`,
        },
    ];
});

// ── Filtros ──
const periodos = [
    { value: 1, label: 'Hoy y próximos' },
    { value: 7, label: 'Últimos 7 días' },
    { value: 14, label: 'Últimos 14 días' },
    { value: 0, label: 'Todo el historial' },
] as const;

// Filtros por estado con su total.
const chips = computed<Chip[]>(() =>
    toValue(porEstado).map((e) => ({
        key: e.estado,
        label: estadoDespachoInfo[e.estado].label,
        count: e.total,
        color: estadoDespachoInfo[e.estado].color,
    })),
);
const alEstado = (k: string) => toggleEstado(k as Despacho['estado']);

const activos = computed<Activo[]>(() => [
    ...(filtros.estado
        ? [{ key: 'estado', label: estadoDespachoInfo[filtros.estado].label }]
        : []),
    ...(filtros.conductor
        ? [{ key: 'conductor', label: `Conductor: ${filtros.conductor}` }]
        : []),
    ...(filtros.vehiculo
        ? [{ key: 'vehiculo', label: `Vehículo: ${filtros.vehiculo}` }]
        : []),
]);

const quitarFiltro = (key: string) => {
    if (key === 'estado') filtros.estado = null;
    if (key === 'conductor') filtros.conductor = null;
    if (key === 'vehiculo') filtros.vehiculo = null;
};

// ── Tabla ──
const columnas: Columna[] = [
    { key: 'fecha', label: 'Despacho' },
    { label: 'Conductor' },
    { label: 'Estado' },
    { label: 'Kilómetros' },
    { key: 'costo_estimado', label: 'Costo' },
];
const ordenar = (k: string) => ordenarPor(k as SortKey);

const columnasRed: Columna[] = [
    { label: 'Tramo' },
    { label: 'Distancia' },
    { label: 'Tiempo' },
    { label: 'Velocidad media' },
    { label: 'Estado' },
];

// ── Análisis en pestañas ──
const tab = ref('conductores');
const tabs = [
    { key: 'conductores', label: 'Conductores' },
    { key: 'vehiculos', label: 'Vehículos' },
    { key: 'km', label: 'Kilómetros por día' },
    { key: 'red', label: 'Conexiones' },
] as const;

const cabeceras: Record<string, { title: string; description: string }> = {
    conductores: {
        title: 'Puntualidad por conductor',
        description:
            'Ordenados de menor a mayor puntualidad. Selecciona uno para filtrar el listado de despachos.',
    },
    vehiculos: {
        title: 'Costo por kilómetro y vehículo',
        description:
            'Los vehículos más caros por kilómetro aparecen primero. Selecciona uno para filtrar el listado.',
    },
    red: {
        title: 'Red de conexiones entre sucursales',
        description: `Velocidad media = distancia ÷ tiempo. Por debajo de ${VELOCIDAD_LENTA} km/h el tramo se marca como lento.`,
    },
};

const leyendaKm = [
    { label: 'Planificados', color: colors.grisMedio },
    { label: 'Recorridos', color: colors.azulClaro },
];

const filasConductor = computed<BarRow[]>(() =>
    toValue(porConductor).map((c) => ({
        key: c.conductor,
        label: c.conductor,
        width: c.pct,
        color: alertaPct(c.pct) ?? colors.azulClaro,
        detail: `${c.despachos} despachos`,
        accent: `${c.pct.toFixed(0)}% puntual`,
        accentColor: alertaPct(c.pct),
    })),
);

const filasVehiculo = computed<BarRow[]>(() =>
    toValue(porVehiculo).map((v) => ({
        key: v.vehiculo,
        label: v.vehiculo,
        mono: true,
        width: v.ancho,
        color: colors.azulClaro,
        detail: `${fmtUsd(v.costoPorKm)}/km · ${v.despachos} despachos`,
        accent: v.desviacion !== null ? `${signo(v.desviacion)} km` : undefined,
        accentColor:
            v.desviacion !== null ? alertaDesv(v.desviacion) : undefined,
    })),
);

// ── Panel de detalle ──
type Parada = Despacho['paradas'][number];

const general = (d: Despacho): Field[] => [
    { label: 'Fecha', value: fmtFecha(d.fecha) },
    { label: 'Sucursal de origen', value: d.sucursal_origen },
    { label: 'Conductor', value: d.conductor, weight: 'medium' },
    { label: 'Vehículo', value: d.vehiculo, weight: 'medium' },
];

const kilometros = (d: Despacho): Field[] => {
    const cpk = costoPorKm(d);
    const dv = desv(d);
    return [
        {
            label: 'Km planificados',
            value: d.km_planificados?.toFixed(1) ?? '—',
            weight: 'semibold',
        },
        {
            label: 'Km recorridos',
            value: `${d.km_recorridos?.toFixed(1) ?? '—'}${dv ? ` (${dv.texto})` : ''}`,
            weight: 'semibold',
        },
        {
            label: 'Costo estimado',
            value: d.costo_estimado !== null ? fmtUsd(d.costo_estimado) : '—',
        },
        { label: 'Costo por km', value: cpk !== null ? fmtUsd(cpk) : '—' },
    ];
};

const paradas = (d: Despacho): TimelineItem[] =>
    d.paradas.map((p: Parada) => {
        const est = estadoParada(p, d, toValue(now));
        const info = estadoParadaInfo[est];
        const hora = p.hora_real
            ? `Estimada ${fmtHora(p.hora_estimada)} · Real ${fmtHora(p.hora_real)} (+${retrasoMin(p)} min)`
            : `Estimada ${fmtHora(p.hora_estimada)}${est === 'atrasada' ? ' · Sin registrar' : ''}`;
        return {
            key: p.id_parada,
            marker: p.orden,
            color: info.color,
            title: p.destino,
            tag: info.label,
            tagColor: info.color,
            meta: hora,
            note: `Guía ${p.codigo_guia}`,
        };
    });

const resumen = (d: Despacho) => {
    const r = resumenParadas(d, toValue(now));
    return `${r.aTiempo} de ${r.evaluadas} a tiempo`;
};

// Ejecución del despacho: iniciar, finalizar e incidencias
const formFinal = reactive<{ km: string; horas: Record<number, string> }>({
    km: '',
    horas: {},
});

const finalizar = (d: Despacho) => {
    router.post(
        `/rutas/${d.id_despacho}/finalizar`,
        {
            km_recorridos: formFinal.km === '' ? null : Number(formFinal.km),
            paradas: d.paradas.map((p) => ({
                id_parada: p.id_parada,
                hora_real: formFinal.horas[p.id_parada] || null,
            })),
        },
        { preserveScroll: true },
    );
};

const iniciar = (d: Despacho) => {
    router.post(
        `/rutas/${d.id_despacho}/iniciar`,
        {},
        { preserveScroll: true },
    );
};

const formIncidencia = reactive<{
    tipo: number | null;
    minutos: string;
    envio: number | null;
    nota: string;
}>({
    tipo: null,
    minutos: '',
    envio: null,
    nota: '',
});

const enviosDelDespacho = (d: Despacho) =>
    d.paradas.map((p) => ({ id: p.envio_id, nombre: `Guía ${p.codigo_guia}` }));

const registrarIncidencia = (d: Despacho) => {
    router.post(
        `/rutas/${d.id_despacho}/incidencias`,
        {
            tipo_incidencia_id: formIncidencia.tipo,
            minutos_retraso: Number(formIncidencia.minutos || 0),
            envio_id: formIncidencia.envio,
            descripcion: formIncidencia.nota || null,
        },
        {
            preserveScroll: true,
            onSuccess: () => {
                formIncidencia.tipo = null;
                formIncidencia.minutos = '';
                formIncidencia.envio = null;
                formIncidencia.nota = '';
            },
        },
    );
};
</script>

<template>
    <Head title="Rutas" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
        <!-- Encabezado -->
        <EncabezadoAcciones
            title="Rutas y despachos"
            subtitle="Planificación, seguimiento y eficiencia de las salidas"
            :accion="permisos.gestionar ? 'Nuevo despacho' : undefined"
            :href="permisos.gestionar ? '/rutas/crear' : undefined"
            @exportar="exportarCsv"
        >
            <template #acciones>
                <Link
                    href="/rutas/mapa"
                    class="inline-flex h-10 items-center gap-2 rounded-lg border border-sidebar-border/70 bg-card px-4 text-sm font-medium text-foreground transition-colors hover:bg-muted"
                >
                    <MapPin class="h-4 w-4" aria-hidden="true" />
                    Ver mapa
                </Link>
            </template>
        </EncabezadoAcciones>

        <!-- Resumen de indicadores -->
        <BiStats :items="estadisticas" :cols="4" />

        <!-- Sección principal para consultar y administrar los despachos -->
        <section
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm"
            aria-label="Listado de despachos"
        >
            <BiFilters
                v-model:busqueda="filtros.busqueda"
                v-model:periodo="filtros.periodo"
                v-model:soloAlertas="filtros.soloAlertas"
                placeholder="Buscar por despacho, conductor o vehículo"
                :periodos="periodos"
                :activos="activos"
                :hay-filtros="hayFiltros"
                alerta-label="Solo con alertas"
                @quitar="quitarFiltro"
                @limpiar="limpiar"
            >
                <BiChips
                    :items="chips"
                    :active="filtros.estado"
                    @toggle="alEstado"
                />
            </BiFilters>

            <BiTable
                :columns="columnas"
                :sort="sort"
                :vacio="!pagina.length"
                empty-title="No se encontraron despachos"
                @sort="ordenar"
            >
                <BiTableRow
                    v-for="d in pagina"
                    :key="d.id_despacho"
                    @select="seleccionado = d"
                >
                    <!-- Código del despacho y franja de color cuando tiene alertas. -->
                    <td
                        class="px-5 py-4"
                        :style="
                            tieneAlerta(d, now)
                                ? {
                                      boxShadow: `inset 3px 0 0 ${colors.naranjaClaro}`,
                                  }
                                : undefined
                        "
                    >
                        <p
                            class="flex items-center gap-1 font-mono text-sm font-medium text-foreground"
                        >
                            {{ d.codigo }}
                            <button
                                type="button"
                                class="rounded p-0.5 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                :aria-label="`Copiar ${d.codigo}`"
                                :title="
                                    codigoCopiado === d.codigo
                                        ? 'Copiado'
                                        : 'Copiar código'
                                "
                                @click.stop="copiarCodigo(d.codigo)"
                            >
                                <Check
                                    v-if="codigoCopiado === d.codigo"
                                    class="h-3.5 w-3.5 text-green-500"
                                />
                                <Copy v-else class="h-3.5 w-3.5" />
                            </button>
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ fmtFecha(d.fecha) }} · {{ d.sucursal_origen }}
                        </p>
                        <span v-if="tieneAlerta(d, now)" class="sr-only"
                            >Con alertas</span
                        >
                    </td>

                    <!-- Conductor y vehículo asignados. -->
                    <td class="px-5 py-4">
                        <p class="text-foreground">{{ d.conductor }}</p>
                        <p class="mt-1 font-mono text-xs text-muted-foreground">
                            {{ d.vehiculo }}
                        </p>
                    </td>

                    <!-- Estado actual y avance de paradas. -->
                    <td class="px-5 py-4">
                        <BiStatus
                            class="text-foreground"
                            :color="estadoDespachoInfo[d.estado].color"
                            :label="estadoDespachoInfo[d.estado].label"
                        />
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ resumenParadas(d, now).completadas }} de
                            {{ d.paradas.length }} paradas
                        </p>
                    </td>

                    <!-- Kilómetros recorridos frente a los planificados. -->
                    <td class="px-5 py-4 whitespace-nowrap">
                        <span class="text-foreground">{{
                            d.km_recorridos?.toFixed(0) ?? '—'
                        }}</span>
                        <span class="text-muted-foreground">
                            de
                            {{ d.km_planificados?.toFixed(0) ?? '—' }} km</span
                        >
                        <span
                            v-if="desv(d)"
                            class="ml-1.5 text-xs font-medium text-muted-foreground"
                            :style="{ color: desv(d)!.color }"
                            >{{ desv(d)!.texto }}</span
                        >
                    </td>

                    <td
                        class="px-5 py-4 whitespace-nowrap text-muted-foreground"
                    >
                        {{
                            d.costo_estimado !== null
                                ? fmtUsd(d.costo_estimado)
                                : '—'
                        }}
                    </td>
                </BiTableRow>
            </BiTable>

            <BiPagination
                v-model="page"
                :total="totalPaginas"
                :count="filtrados.length"
                unit="despachos"
            />
        </section>

        <!-- Sección de análisis con pestañas -->
        <section
            class="rounded-xl border border-sidebar-border/70 bg-card p-4 shadow-sm sm:p-5"
            aria-label="Análisis"
        >
            <BiTabs v-model="tab" :tabs="tabs" />

            <!-- Gráfico de kilómetros planificados y recorridos por día. -->
            <div v-if="tab === 'km'" class="pt-5">
                <BiPanelHead title="Kilómetros de los últimos 14 días">
                    <BiLegend :items="leyendaKm" />
                </BiPanelHead>

                <div
                    class="flex h-48 items-end justify-between gap-1.5 sm:gap-2"
                    role="img"
                    aria-label="Kilómetros planificados y recorridos por día"
                >
                    <div
                        v-for="(b, i) in kmPorDia.buckets"
                        :key="b.clave"
                        class="flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-2"
                        :title="`${b.label}: ${b.plan.toFixed(0)} km planificados, ${b.real.toFixed(0)} km recorridos`"
                    >
                        <div
                            class="flex h-full w-full items-end justify-center gap-0.5"
                        >
                            <div
                                class="w-full max-w-4 rounded-t-md"
                                :style="{
                                    height: `${(b.plan / kmPorDia.max) * 88}%`,
                                    backgroundColor: colors.grisMedio,
                                }"
                            />
                            <div
                                class="w-full max-w-4 rounded-t-md"
                                :style="{
                                    height: `${(b.real / kmPorDia.max) * 88}%`,
                                    backgroundColor: colors.azulClaro,
                                }"
                            />
                        </div>
                        <span
                            class="text-xs text-muted-foreground"
                            :class="i % 2 ? 'invisible sm:visible' : ''"
                            >{{ b.label }}</span
                        >
                    </div>
                </div>
            </div>

            <!-- Conductores, vehículos y red de conexiones. -->
            <div v-else class="pt-5">
                <BiPanelHead v-bind="cabeceras[tab]" />

                <BiBarList
                    v-if="tab === 'conductores'"
                    :rows="filasConductor"
                    :active="filtros.conductor"
                    empty="No hay paradas evaluadas con los filtros actuales."
                    @select="toggleConductor"
                />

                <BiBarList
                    v-else-if="tab === 'vehiculos'"
                    :rows="filasVehiculo"
                    :active="filtros.vehiculo"
                    empty="No hay datos disponibles con los filtros actuales."
                    @select="toggleVehiculo"
                />

                <div
                    v-else
                    class="overflow-hidden rounded-lg border border-sidebar-border/70"
                >
                    <BiTable
                        :columns="columnasRed"
                        :vacio="!conexionesAnalizadas.length"
                    >
                        <BiTableRow
                            v-for="c in conexionesAnalizadas"
                            :key="c.id_conexion"
                            :selectable="false"
                        >
                            <td class="px-5 py-4 text-foreground">
                                {{ c.origen }} a {{ c.destino }}
                            </td>
                            <td
                                class="px-5 py-4 whitespace-nowrap text-muted-foreground"
                            >
                                {{ c.distancia_km }} km
                            </td>
                            <td
                                class="px-5 py-4 whitespace-nowrap text-muted-foreground"
                            >
                                {{ c.tiempo_min }} min
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <div
                                        class="h-2 w-24 overflow-hidden rounded-full bg-muted"
                                    >
                                        <div
                                            class="h-full rounded-full"
                                            :style="{
                                                width: `${(c.velocidad / velocidadMax) * 100}%`,
                                                backgroundColor: c.lenta
                                                    ? colors.rojo
                                                    : colors.azulClaro,
                                            }"
                                        />
                                    </div>
                                    <span
                                        class="whitespace-nowrap"
                                        :style="{
                                            color: c.lenta
                                                ? colors.rojo
                                                : undefined,
                                        }"
                                        >{{ c.velocidad.toFixed(0) }} km/h</span
                                    >
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <BiBadge
                                    v-if="!c.activa"
                                    :color="colors.grisMedio"
                                    label="Inactiva"
                                />
                                <BiBadge
                                    v-else-if="c.lenta"
                                    :color="colors.amarillo"
                                    label="Tramo lento"
                                />
                                <span
                                    v-else
                                    class="text-sm text-muted-foreground"
                                    >Normal</span
                                >
                            </td>
                        </BiTableRow>
                    </BiTable>
                </div>
            </div>
        </section>

        <!-- Panel lateral con el detalle del despacho -->
        <BiDrawer
            v-if="seleccionado"
            tipo="Detalle del despacho"
            :title="seleccionado.codigo"
            @close="seleccionado = null"
        >
            <BiStateBox>
                <BiStatus
                    class="rounded-lg border border-sidebar-border/70 bg-card px-3 py-2 text-sm font-medium text-foreground"
                    :color="estadoDespachoInfo[seleccionado.estado].color"
                    :label="estadoDespachoInfo[seleccionado.estado].label"
                />
                <BiBadge
                    v-if="tieneAlerta(seleccionado, now)"
                    size="md"
                    :color="colors.naranjaClaro"
                    label="Con alertas"
                />
            </BiStateBox>

            <BiFields
                title="Información general"
                :fields="general(seleccionado)"
                responsive
            />
            <BiFields
                title="Kilómetros y costos"
                :fields="kilometros(seleccionado)"
            />

            <BiTimeline
                title="Paradas"
                :badge="resumen(seleccionado)"
                :items="paradas(seleccionado)"
                empty="No hay paradas registradas para este despacho."
            />

            <!-- Ejecución del despacho: avance de estado e incidencias -->
            <div
                v-if="
                    permisos.gestionar && seleccionado.estado !== 'COMPLETADO'
                "
                class="space-y-4"
            >
                <!-- PLANIFICADO: iniciar la salida -->
                <form
                    v-if="seleccionado.estado === 'PLANIFICADO'"
                    class="rounded-lg border border-sidebar-border/70 p-4"
                    @submit.prevent="iniciar(seleccionado)"
                >
                    <button
                        type="submit"
                        class="inline-flex h-10 w-full items-center justify-center rounded-md bg-blue-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Iniciar despacho
                    </button>
                </form>

                <!-- EN_CURSO: cerrar la ejecución con km y horas reales -->
                <form
                    v-else
                    class="space-y-4 rounded-lg border border-sidebar-border/70 p-4"
                    @submit.prevent="finalizar(seleccionado)"
                >
                    <InputField
                        id="km-recorridos"
                        v-model="formFinal.km"
                        label="Kilómetros recorridos"
                        type="number"
                        min="0"
                        step="0.1"
                        :hint="`Planificados: ${seleccionado.km_planificados?.toFixed(1) ?? '—'} km`"
                    />
                    <InputField
                        v-for="p in seleccionado.paradas"
                        :key="p.id_parada"
                        :id="`hora-real-${p.id_parada}`"
                        v-model="formFinal.horas[p.id_parada]"
                        :label="`Hora real · parada ${p.orden} (${p.destino})`"
                        type="datetime-local"
                        :hint="
                            p.hora_estimada
                                ? `Estimada: ${fmtHora(p.hora_estimada)}`
                                : undefined
                        "
                    />
                    <button
                        type="submit"
                        class="inline-flex h-10 w-full items-center justify-center rounded-md bg-blue-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Finalizar despacho
                    </button>
                </form>

                <!-- Incidencia reportada por el conductor, en su nombre -->
                <form
                    class="space-y-4 rounded-lg border border-sidebar-border/70 p-4"
                    @submit.prevent="registrarIncidencia(seleccionado)"
                >
                    <p class="text-sm font-medium text-foreground">
                        Registrar incidencia
                    </p>
                    <SelectField
                        id="tipo-incidencia"
                        v-model="formIncidencia.tipo"
                        label="Tipo"
                        placeholder="Seleccionar tipo"
                        :options="tipos_incidencia"
                        required
                    />
                    <InputField
                        id="minutos-retraso"
                        v-model="formIncidencia.minutos"
                        label="Minutos de retraso"
                        type="number"
                        min="0"
                        required
                    />
                    <SelectField
                        id="envio-incidencia"
                        v-model="formIncidencia.envio"
                        label="Envío afectado"
                        placeholder="Todo el despacho"
                        :options="enviosDelDespacho(seleccionado)"
                    />
                    <InputField
                        id="descripcion-incidencia"
                        v-model="formIncidencia.nota"
                        label="Descripción"
                        type="text"
                        placeholder="Detalle de la novedad"
                    />
                    <button
                        type="submit"
                        class="inline-flex h-10 w-full items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-medium text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Registrar incidencia
                    </button>
                </form>
            </div>
        </BiDrawer>
    </div>
</template>
