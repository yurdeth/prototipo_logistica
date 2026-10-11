<script setup lang="ts">
import { computed, ref, toValue } from 'vue';
import { Head } from '@inertiajs/vue3';
import { colors } from '@/UI/Colors';
import { dashboard } from '@/routes';

// Configuración de estados, prioridades, SLA y datos de prueba.
import {
    estadoInfo,
    prioridadInfo,
    slaInfo,
    generarEnviosDemo,
    type Envio,
} from '@/config/Envios/envios';

// Lógica de negocio de Envíos.
import {
    useEnviosBI,
    getSla,
    fmtFecha,
    fmtDuracion,
    type SortKey,
} from '@/composables/useEnviosBI';

// Componentes BI reutilizables.
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

// Tipos compartidos.
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
            { title: 'Envíos', href: '#' },
        ],
    },
});

// Si Laravel no envía datos, se utilizan los de demostración.
const props = withDefaults(defineProps<{ envios?: Envio[] }>(), {
    envios: () => generarEnviosDemo(),
});

// Datos, filtros, métricas, ordenamiento y paginación.
const {
    now,
    filtros,
    sort,
    page,
    filtrados,
    kpis,
    embudo,
    tendencia,
    porPrioridad,
    topDestinos,
    pagina,
    totalPaginas,
    ordenarPor,
    toggleEstado,
    togglePrioridad,
    toggleDestino,
    hayFiltros,
    limpiar,
    exportarCsv,
} = useEnviosBI(computed(() => props.envios));

// ── Tarjetas de resumen ──
const resumen = computed<Stat[]>(() => [
    {
        label: 'Envíos',
        value: kpis.value.total,
        hint: `${kpis.value.pesoTotal.toLocaleString('es-SV', { maximumFractionDigits: 0 })} kg transportados`,
    },
    {
        label: 'Cumplimiento de entrega',
        value:
            kpis.value.cumplimiento === null
                ? '—'
                : `${kpis.value.cumplimiento.toFixed(1)}%`,
        hint: `${kpis.value.aTiempo} de ${kpis.value.cerrados} cerrados a tiempo`,
    },
    {
        label: 'Requieren atención',
        value: kpis.value.enRiesgo + kpis.value.vencidos,
        hint: `${kpis.value.vencidos} vencidos y ${kpis.value.enRiesgo} por vencer en 24h`,
        color: kpis.value.vencidos > 0 ? colors.rojo : undefined,
    },
]);

// Clasificación de alertas SLA.
const SLA_ALERTA = ['vencido', 'en_riesgo'];
const esAlerta = (sla: string) => SLA_ALERTA.includes(sla);

// ── Filtros ──
const periodos = [
    { value: 7, label: 'Últimos 7 días' },
    { value: 30, label: 'Últimos 30 días' },
    { value: 90, label: 'Últimos 90 días' },
    { value: 0, label: 'Todo el historial' },
] as const;

// Filtros por estado con su total.
const chips = computed<Chip[]>(() =>
    toValue(embudo).map((f) => ({
        key: f.estado,
        label: estadoInfo[f.estado].label,
        count: f.total,
        color: estadoInfo[f.estado].color,
    })),
);
const alEstado = (k: string) => toggleEstado(k as Envio['estado']);
const alPrioridad = (k: string) => togglePrioridad(k as Envio['prioridad']);

// Filtros activos que se muestran en BiFilters.
const activos = computed<Activo[]>(() => [
    ...(filtros.estado
        ? [
              {
                  key: 'estado',
                  label: `Estado: ${estadoInfo[filtros.estado].label}`,
              },
          ]
        : []),
    ...(filtros.prioridad
        ? [
              {
                  key: 'prioridad',
                  label: `Prioridad: ${prioridadInfo[filtros.prioridad].label}`,
              },
          ]
        : []),
    ...(filtros.destino
        ? [{ key: 'destino', label: `Destino: ${filtros.destino}` }]
        : []),
    ...(filtros.soloRiesgo
        ? [{ key: 'soloRiesgo', label: 'Solo con alerta' }]
        : []),
]);

const quitarFiltro = (key: string) => {
    if (key === 'estado') filtros.estado = null;
    if (key === 'prioridad') filtros.prioridad = null;
    if (key === 'destino') filtros.destino = null;
    if (key === 'soloRiesgo') filtros.soloRiesgo = false;
};

// ── Tabla ──
const columnas: Columna[] = [
    { label: 'Envío' },
    { label: 'Destino' },
    { label: 'Estado' },
    { key: 'fecha_comprometida', label: 'Compromiso' },
    { label: 'Situación' },
];
const ordenar = (k: string) => ordenarPor(k as SortKey);

// ── Análisis en pestañas ──
const tab = ref('tendencia');
const tabs = [
    { key: 'tendencia', label: 'Registros por día' },
    { key: 'prioridad', label: 'Por prioridad' },
    { key: 'destinos', label: 'Destinos' },
] as const;

const leyendaTendencia = [
    { label: 'Sin incidencia', color: colors.azulClaro },
    { label: 'Con incidencia', color: colors.rojo },
];

const filasPrioridad = computed<BarRow[]>(() =>
    toValue(porPrioridad).map((p) => ({
        key: p.prioridad,
        label: prioridadInfo[p.prioridad].label,
        dot: prioridadInfo[p.prioridad].color,
        badge: p.criticos ? `${p.criticos} con alerta` : undefined,
        badgeColor: colors.rojo,
        detail: `${p.total} (${p.porcentaje.toFixed(0)}%)`,
    })),
);

const filasDestino = computed<BarRow[]>(() =>
    toValue(topDestinos).map((d) => ({
        key: d.destino,
        label: d.destino,
        width: d.ancho,
        color: d.pctIncidencias >= 30 ? colors.rojo : colors.azulClaro,
        detail: `${d.total} envíos`,
        accent: `${d.pctIncidencias.toFixed(0)}% con incidencia`,
        accentColor: d.pctIncidencias >= 30 ? colors.rojo : undefined,
    })),
);

// ── Panel de detalle ──
const seleccionado = ref<Envio | null>(null);

const personas = (e: Envio): Field[] => [
    { label: 'Remitente', value: e.remitente, weight: 'medium' },
    { label: 'Destinatario', value: e.destinatario, weight: 'medium' },
];

const logistica = (e: Envio): Field[] => [
    { label: 'Sucursal de origen', value: e.sucursal_origen },
    { label: 'Destino', value: e.territorio_destino },
    { label: 'Dirección de entrega', value: e.direccion_destino, wide: true },
];

const especificaciones = (e: Envio): Field[] => [
    { label: 'Peso', value: `${e.peso_kg.toFixed(2)} kg`, weight: 'semibold' },
    {
        label: 'Volumen',
        value: e.volumen_m3 ? `${e.volumen_m3} m³` : '—',
        weight: 'semibold',
    },
    { label: 'Fecha de registro', value: fmtFecha(e.fecha_registro) },
    { label: 'Fecha compromiso', value: fmtFecha(e.fecha_comprometida) },
];

const eventos = (e: Envio): TimelineItem[] =>
    e.eventos.map((ev) => ({
        key: ev.id_evento,
        color: estadoInfo[ev.estado].color,
        title: estadoInfo[ev.estado].label,
        meta: fmtFecha(ev.ocurrido_en),
        note: ev.nota || undefined,
    }));
</script>

<template>
    <Head title="Envíos" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
        <!-- Encabezado -->
        <EncabezadoAcciones
            title="Envíos"
            subtitle="Seguimiento de entregas y cumplimiento de compromisos"
            accion="Nuevo envío"
            href="/envios/crear"
            @exportar="exportarCsv"
        />

        <!-- Sección de resumen con indicadores clave de los envíos. -->
        <StatCards :items="resumen" :cols="3" />

        <!-- Sección principal para consultar y administrar los envíos. -->
        <section
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm"
            aria-label="Listado de envíos"
        >
            <!-- Panel de búsqueda y filtros del listado. -->
            <BiFilters
                v-model:busqueda="filtros.busqueda"
                v-model:periodo="filtros.periodo"
                v-model:solo-alertas="filtros.soloRiesgo"
                placeholder="Buscar por guía, cliente o destino"
                :periodos="periodos"
                :activos="activos"
                :hay-filtros="hayFiltros"
                alerta-label="Solo con alerta"
                @quitar="quitarFiltro"
                @limpiar="limpiar"
            >
                <BiChips
                    :items="chips"
                    :active="filtros.estado"
                    @toggle="alEstado"
                />
            </BiFilters>

            <!-- Tabla con la información principal y el estado de cada envío. -->
            <BiTable
                :columns="columnas"
                :sort="sort"
                :vacio="!pagina.length"
                empty-title="No se encontraron envíos"
                @sort="ordenar"
            >
                <BiTableRow
                    v-for="e in pagina"
                    :key="e.id_envio"
                    @select="seleccionado = e"
                >
                    <!-- Identificación del envío y alerta visual según el SLA. -->
                    <td
                        class="px-5 py-4"
                        :style="
                            esAlerta(getSla(e, now))
                                ? {
                                      boxShadow: `inset 3px 0 0 ${slaInfo[getSla(e, now)].color}`,
                                  }
                                : undefined
                        "
                    >
                        <p
                            class="font-mono text-sm font-medium text-foreground"
                        >
                            {{ e.codigo_guia }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ e.remitente }} a {{ e.destinatario }}
                        </p>
                    </td>

                    <!-- Destino registrado para el envío. -->
                    <td class="px-5 py-4 text-foreground">
                        {{ e.territorio_destino }}
                    </td>

                    <!-- Estado actual identificado mediante un indicador de color. -->
                    <td class="px-5 py-4">
                        <BiStatus
                            class="text-foreground"
                            :color="estadoInfo[e.estado].color"
                            :label="estadoInfo[e.estado].label"
                        />
                    </td>

                    <!-- Fecha límite de entrega, con formato legible. -->
                    <td
                        class="px-5 py-4 whitespace-nowrap text-muted-foreground"
                    >
                        {{ fmtFecha(e.fecha_comprometida) }}
                    </td>

                    <!-- Situación del SLA para detectar retrasos y riesgos. -->
                    <td class="px-5 py-4">
                        <BiBadge
                            v-if="esAlerta(getSla(e, now))"
                            :color="slaInfo[getSla(e, now)].color"
                            :label="slaInfo[getSla(e, now)].label"
                        />
                        <span
                            v-else
                            class="text-sm whitespace-nowrap text-muted-foreground"
                            >{{ slaInfo[getSla(e, now)].label }}</span
                        >
                    </td>
                </BiTableRow>
            </BiTable>

            <!-- Controles para navegar entre las páginas del listado. -->
            <BiPagination
                v-model="page"
                :total="totalPaginas"
                :count="filtrados.length"
                unit="envíos"
            />
        </section>

        <!-- Sección de análisis estadístico con pestañas interactivas. -->
        <section
            class="rounded-xl border border-sidebar-border/70 bg-card p-4 shadow-sm sm:p-5"
            aria-label="Análisis"
        >
            <BiTabs v-model="tab" :tabs="tabs">
                <!-- Indicadores complementarios de desempeño logístico. -->
                <p class="text-sm leading-relaxed text-muted-foreground">
                    Entrega promedio
                    <span class="font-semibold text-foreground">{{
                        fmtDuracion(kpis.tiempoPromedioH)
                    }}</span
                    >, fallos o devoluciones
                    <span class="font-semibold text-foreground"
                        >{{ kpis.tasaFallo.toFixed(1) }}%</span
                    >
                </p>
            </BiTabs>

            <!-- Gráfico de tendencia diaria de envíos e incidencias. -->
            <div v-if="tab === 'tendencia'" class="pt-5">
                <BiPanelHead title="Registros de los últimos 14 días">
                    <BiLegend :items="leyendaTendencia" />
                </BiPanelHead>

                <!-- Barras proporcionales al volumen diario de envíos. -->
                <div
                    class="flex h-48 items-end justify-between gap-1.5 sm:gap-2"
                    role="img"
                    aria-label="Envíos registrados por día en los últimos 14 días"
                >
                    <div
                        v-for="(b, i) in tendencia.buckets"
                        :key="b.label"
                        class="flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-2"
                        :title="`${b.label}: ${b.total} envíos, ${b.incidencias} con incidencia`"
                    >
                        <span
                            class="text-xs font-medium text-muted-foreground"
                            >{{ b.total || '' }}</span
                        >

                        <div
                            class="flex w-full max-w-8 flex-col-reverse overflow-hidden rounded-t-md"
                            :style="{
                                height: `${(b.total / tendencia.max) * 80}%`,
                            }"
                        >
                            <div
                                :style="{
                                    height: `${b.total ? ((b.total - b.incidencias) / b.total) * 100 : 0}%`,
                                    backgroundColor: colors.azulClaro,
                                }"
                            />
                            <div
                                :style="{
                                    height: `${b.total ? (b.incidencias / b.total) * 100 : 0}%`,
                                    backgroundColor: colors.rojo,
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

            <!-- Distribución de envíos según su nivel de prioridad. -->
            <div v-else-if="tab === 'prioridad'" class="pt-5">
                <BiPanelHead
                    title="Envíos por prioridad"
                    description="Selecciona un nivel para filtrar el listado de envíos."
                />
                <BiBarList
                    :rows="filasPrioridad"
                    :active="filtros.prioridad"
                    @select="alPrioridad"
                />
            </div>

            <!-- Comparación de destinos según volumen e incidencias. -->
            <div v-else class="pt-5">
                <BiPanelHead
                    title="Rendimiento por destino"
                    description="Los destinos con incidencias iguales o superiores al 30% se destacan en rojo."
                />
                <BiBarList
                    :rows="filasDestino"
                    :active="filtros.destino"
                    empty="No hay datos disponibles con los filtros actuales."
                    @select="toggleDestino"
                />
            </div>
        </section>

        <!-- Panel lateral con la información detallada del envío seleccionado. -->
        <BiDrawer
            v-if="seleccionado"
            tipo="Detalle del envío"
            :title="seleccionado.codigo_guia"
            @close="seleccionado = null"
        >
            <!-- Estado, prioridad y situación actual del SLA. -->
            <BiStateBox>
                <BiStatus
                    class="rounded-lg border border-sidebar-border/70 bg-card px-3 py-2 text-sm font-medium text-foreground"
                    :color="estadoInfo[seleccionado.estado].color"
                    :label="estadoInfo[seleccionado.estado].label"
                />
                <span
                    class="rounded-lg border border-sidebar-border/70 bg-card px-3 py-2 text-sm text-foreground"
                >
                    Prioridad
                    {{
                        prioridadInfo[
                            seleccionado.prioridad
                        ].label.toLowerCase()
                    }}
                </span>
                <BiBadge
                    v-if="esAlerta(getSla(seleccionado, now))"
                    size="md"
                    :color="slaInfo[getSla(seleccionado, now)].color"
                    :label="slaInfo[getSla(seleccionado, now)].label"
                />
            </BiStateBox>

            <BiFields
                title="Información general"
                :fields="personas(seleccionado)"
                responsive
            />
            <BiFields
                title="Información logística"
                :fields="logistica(seleccionado)"
            />
            <BiFields
                title="Especificaciones y fechas"
                :fields="especificaciones(seleccionado)"
            />

            <!-- Línea de tiempo con los cambios registrados en el envío. -->
            <BiTimeline
                title="Historial de seguimiento"
                :badge="`${seleccionado.eventos.length} eventos`"
                :items="eventos(seleccionado)"
                empty="No hay eventos registrados para este envío."
            />
        </BiDrawer>
    </div>
</template>
