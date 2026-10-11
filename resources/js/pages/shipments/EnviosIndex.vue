<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { colors } from '@/UI/Colors';
import {
    Plus,
    Download,
    Search,
    X,
    ChevronLeft,
    ChevronRight,
    ChevronUp,
    ChevronDown,
} from '@/UI/Icons';
// Ruta del dashboard para configurar la navegación.
import { dashboard } from '@/routes';
// Configuración de estados, prioridades, SLA y datos de prueba.
import {
    estadoInfo,
    prioridadInfo,
    slaInfo,
    generarEnviosDemo,
    type Envio,
} from '@/config/Envios/envios';
import {
    useEnviosBI,
    getSla,
    fmtFecha,
    fmtDuracion,
    type SortKey,
} from '@/composables/useEnviosBI';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Envíos', href: '#' },
        ],
    },
});

// Si Laravel no envía datos, se usan los de demostración
const props = withDefaults(defineProps<{ envios?: Envio[] }>(), {
    envios: () => generarEnviosDemo(),
});

// Obtiene los datos y las funciones para gestionar filtros, métricas y paginación.
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

const soft = (hex: string, alpha = '1A') => `${hex}${alpha}`;

const SLA_ALERTA = ['vencido', 'en_riesgo'];
const esAlerta = (sla: string) => SLA_ALERTA.includes(sla);

const seleccionado = ref<Envio | null>(null);
const tab = ref<'tendencia' | 'prioridad' | 'destinos'>('tendencia');

// Define las pestañas disponibles en el panel.
const tabs = [
    { key: 'tendencia', label: 'Registros por día' },
    { key: 'prioridad', label: 'Por prioridad' },
    { key: 'destinos', label: 'Destinos' },
] as const;

const columnas: { key: SortKey; label: string }[] = [
    { key: 'fecha_comprometida', label: 'Compromiso' },
];

// Opciones para filtrar los envíos por período.
const periodos = [
    { value: 7, label: 'Últimos 7 días' },
    { value: 30, label: 'Últimos 30 días' },
    { value: 90, label: 'Últimos 90 días' },
    { value: 0, label: 'Todo el historial' },
] as const;

const controlClase =
    'h-10 rounded-lg border border-sidebar-border/70 bg-card px-3 text-sm text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';
</script>

<template>
    <Head title="Envíos" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
        <!-- Encabezado -->
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1
                    class="text-2xl font-semibold tracking-tight text-foreground"
                >
                    Envíos
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Seguimiento de entregas y cumplimiento de compromisos
                </p>
            </div>

            <div class="flex gap-2">
                <!--Button para poder exportar datos en formato CSV-->
                <button
                    type="button"
                    class="inline-flex h-10 items-center gap-2 rounded-lg border border-sidebar-border/70 bg-card px-4 text-sm font-medium text-foreground hover:bg-muted"
                    @click="exportarCsv"
                >
                    <Download class="h-4 w-4" aria-hidden="true" />
                    Exportar
                </button>

                <!-- Navega al formulario de creación de envíos. -->
                <Link
                    href="/envios/crear"
                    class="inline-flex h-10 items-center gap-2 rounded-lg px-4 text-sm font-medium text-white transition-opacity hover:opacity-90"
                    :style="{ backgroundColor: colors.azulClaro }"
                >
                    <Plus class="h-4 w-4" aria-hidden="true" />
                    Nuevo envío
                </Link>
            </div>
        </header>

        <!-- Sección de resumen con indicadores clave de los envíos. -->
        <section
            aria-label="Resumen"
            class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3"
        >
            <!-- Tarjeta: total de envíos y peso transportado. -->
            <div
                class="rounded-xl border border-sidebar-border/70 bg-card p-5 shadow-sm transition-shadow hover:shadow-md"
            >
                <p class="text-sm font-medium text-muted-foreground">Envíos</p>
                <p
                    class="mt-2 text-3xl font-semibold tracking-tight text-foreground"
                >
                    {{ kpis.total }}
                </p>
                <p class="mt-2 text-sm text-muted-foreground">
                    {{
                        kpis.pesoTotal.toLocaleString('es-SV', {
                            maximumFractionDigits: 0,
                        })
                    }}
                    kg transportados
                </p>
            </div>

            <!-- Tarjeta: porcentaje de entregas realizadas a tiempo. -->
            <div
                class="rounded-xl border border-sidebar-border/70 bg-card p-5 shadow-sm transition-shadow hover:shadow-md"
            >
                <p class="text-sm font-medium text-muted-foreground">
                    Cumplimiento de entrega
                </p>
                <p
                    class="mt-2 text-3xl font-semibold tracking-tight text-foreground"
                >
                    {{
                        kpis.cumplimiento === null
                            ? '—'
                            : `${kpis.cumplimiento.toFixed(1)}%`
                    }}
                </p>
                <p class="mt-2 text-sm text-muted-foreground">
                    {{ kpis.aTiempo }} de {{ kpis.cerrados }} cerrados a tiempo
                </p>
            </div>

            <!-- Tarjeta: envíos vencidos o próximos a incumplir el SLA. -->
            <div
                class="rounded-xl border border-sidebar-border/70 bg-card p-5 shadow-sm transition-shadow hover:shadow-md"
            >
                <p class="text-sm font-medium text-muted-foreground">
                    Requieren atención
                </p>
                <p
                    class="mt-2 text-3xl font-semibold tracking-tight"
                    :style="{ color: kpis.vencidos ? colors.rojo : undefined }"
                    :class="kpis.vencidos ? '' : 'text-foreground'"
                >
                    {{ kpis.enRiesgo + kpis.vencidos }}
                </p>
                <p class="mt-2 text-sm text-muted-foreground">
                    {{ kpis.vencidos }} vencidos y {{ kpis.enRiesgo }} por
                    vencer en 24h
                </p>
            </div>
        </section>

        <!-- Sección principal para consultar y administrar los envíos. -->
        <section
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm"
            aria-label="Listado de envíos"
        >
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
                            v-model="filtros.busqueda"
                            type="search"
                            placeholder="Buscar por guía, cliente o destino"
                            aria-label="Buscar envíos"
                            :class="[controlClase, 'w-full']"
                            class="pl-9"
                        />
                    </div>

                    <select
                        v-model.number="filtros.periodo"
                        aria-label="Período"
                        :class="controlClase"
                        class="w-full sm:w-auto"
                    >
                        <option
                            v-for="p in periodos"
                            :key="p.value"
                            :value="p.value"
                        >
                            {{ p.label }}
                        </option>
                    </select>

                    <button
                        type="button"
                        class="inline-flex h-10 items-center justify-center gap-2 rounded-lg border px-4 text-sm font-medium transition-colors hover:bg-muted"
                        :class="
                            filtros.soloRiesgo
                                ? 'border-orange-500/50 bg-orange-500/10 text-orange-700 dark:text-orange-300'
                                : 'border-sidebar-border/70 bg-card text-foreground'
                        "
                        :aria-pressed="filtros.soloRiesgo"
                        @click="filtros.soloRiesgo = !filtros.soloRiesgo"
                    >
                        Solo con alerta
                    </button>
                </div>

                <!-- Filtros por estado con el total de envíos correspondientes. -->
                <div
                    class="flex flex-wrap items-center gap-2"
                    role="group"
                    aria-label="Filtrar por estado"
                >
                    <button
                        v-for="fila in embudo"
                        :key="fila.estado"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium transition-colors"
                        :class="
                            filtros.estado === fila.estado
                                ? 'border-foreground bg-foreground text-background'
                                : 'border-sidebar-border/70 bg-card text-foreground hover:bg-muted'
                        "
                        :aria-pressed="filtros.estado === fila.estado"
                        @click="toggleEstado(fila.estado)"
                    >
                        <span
                            class="h-2 w-2 rounded-full"
                            :style="{
                                backgroundColor: estadoInfo[fila.estado].color,
                            }"
                        />
                        {{ estadoInfo[fila.estado].label }}
                        <span
                            class="rounded-md bg-muted/70 px-1.5 py-0.5 text-xs text-muted-foreground"
                        >
                            {{ fila.total }}
                        </span>
                    </button>
                </div>

                <!-- Muestra los filtros activos y permite eliminarlos individualmente. -->
                <div
                    v-if="filtros.prioridad || filtros.destino || hayFiltros"
                    class="flex flex-wrap items-center gap-2 text-sm"
                >
                    <span class="font-medium text-muted-foreground">
                        Filtros activos:
                    </span>

                    <span
                        v-if="filtros.prioridad"
                        class="inline-flex items-center gap-2 rounded-lg border border-sidebar-border/70 bg-card px-3 py-1.5 text-foreground"
                    >
                        Prioridad
                        {{
                            prioridadInfo[filtros.prioridad].label.toLowerCase()
                        }}
                        <button
                            type="button"
                            class="rounded p-0.5 hover:bg-muted"
                            aria-label="Quitar filtro de prioridad"
                            @click="filtros.prioridad = null"
                        >
                            <X class="h-3.5 w-3.5" />
                        </button>
                    </span>

                    <span
                        v-if="filtros.destino"
                        class="inline-flex items-center gap-2 rounded-lg border border-sidebar-border/70 bg-card px-3 py-1.5 text-foreground"
                    >
                        {{ filtros.destino }}
                        <button
                            type="button"
                            class="rounded p-0.5 hover:bg-muted"
                            aria-label="Quitar filtro de destino"
                            @click="filtros.destino = null"
                        >
                            <X class="h-3.5 w-3.5" />
                        </button>
                    </span>

                    <button
                        type="button"
                        class="font-medium text-blue-700 hover:underline dark:text-blue-300"
                        @click="limpiar"
                    >
                        Limpiar filtros
                    </button>
                </div>
            </div>

            <!-- Tabla con la información principal y el estado de cada envío. -->
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-muted/40">
                        <tr
                            class="border-b border-sidebar-border/70 text-xs tracking-wide text-muted-foreground uppercase"
                        >
                            <th class="px-5 py-3.5 font-semibold">Envío</th>
                            <th class="px-5 py-3.5 font-semibold">Destino</th>
                            <th class="px-5 py-3.5 font-semibold">Estado</th>

                            <th
                                v-for="c in columnas"
                                :key="c.key"
                                class="px-5 py-3.5 font-semibold"
                            >
                                <button
                                    type="button"
                                    class="inline-flex items-center gap-1.5 hover:text-foreground"
                                    @click="ordenarPor(c.key)"
                                >
                                    {{ c.label }}
                                    <ChevronUp
                                        v-if="
                                            sort.key === c.key &&
                                            sort.dir === 'asc'
                                        "
                                        class="h-3.5 w-3.5"
                                    />
                                    <ChevronDown
                                        v-else-if="sort.key === c.key"
                                        class="h-3.5 w-3.5"
                                    />
                                </button>
                            </th>

                            <th class="px-5 py-3.5 font-semibold">Situación</th>
                        </tr>
                    </thead>

                    <tbody>
                        <!-- Cada fila permite consultar el detalle del envío seleccionado. -->
                        <tr
                            v-for="e in pagina"
                            :key="e.id_envio"
                            class="cursor-pointer border-b border-sidebar-border/40 transition-colors last:border-b-0 hover:bg-muted/40 focus-visible:bg-muted/40 focus-visible:outline-none"
                            tabindex="0"
                            @click="seleccionado = e"
                            @keydown.enter="seleccionado = e"
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
                                <span
                                    class="inline-flex items-center gap-2 text-foreground"
                                >
                                    <span
                                        class="h-2 w-2 shrink-0 rounded-full"
                                        :style="{
                                            backgroundColor:
                                                estadoInfo[e.estado].color,
                                        }"
                                    />
                                    {{ estadoInfo[e.estado].label }}
                                </span>
                            </td>

                            <!-- Fecha límite de entrega, con formato legible. -->
                            <td
                                class="px-5 py-4 whitespace-nowrap text-muted-foreground"
                            >
                                {{ fmtFecha(e.fecha_comprometida) }}
                            </td>

                            <!-- Situación del SLA para detectar retrasos y riesgos. -->
                            <td class="px-5 py-4">
                                <span
                                    v-if="esAlerta(getSla(e, now))"
                                    class="inline-flex rounded-md px-2.5 py-1 text-xs font-semibold whitespace-nowrap"
                                    :style="{
                                        color: slaInfo[getSla(e, now)].color,
                                        backgroundColor: soft(
                                            slaInfo[getSla(e, now)].color,
                                        ),
                                    }"
                                >
                                    {{ slaInfo[getSla(e, now)].label }}
                                </span>

                                <span
                                    v-else
                                    class="text-sm whitespace-nowrap text-muted-foreground"
                                >
                                    {{ slaInfo[getSla(e, now)].label }}
                                </span>
                            </td>
                        </tr>

                        <!-- Mensaje cuando no existen resultados para los filtros aplicados. -->
                        <tr v-if="!pagina.length">
                            <td
                                colspan="5"
                                class="px-5 py-14 text-center text-sm text-muted-foreground"
                            >
                                <p class="font-medium text-foreground">
                                    No se encontraron envíos
                                </p>
                                <p class="mt-1">
                                    Prueba modificar o eliminar alguno de los
                                    filtros.
                                </p>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Controles para navegar entre las páginas del listado. -->
            <div
                class="flex flex-col gap-3 border-t border-sidebar-border/70 bg-muted/20 px-5 py-4 text-sm text-muted-foreground sm:flex-row sm:items-center sm:justify-between"
            >
                <span>
                    {{ filtrados.length }} envíos · Página {{ page }} de
                    {{ totalPaginas }}
                </span>

                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        class="rounded-lg border border-sidebar-border/70 bg-card p-2 text-foreground transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-40"
                        :disabled="page === 1"
                        aria-label="Página anterior"
                        @click="page--"
                    >
                        <ChevronLeft class="h-4 w-4" />
                    </button>

                    <button
                        type="button"
                        class="rounded-lg border border-sidebar-border/70 bg-card p-2 text-foreground transition-colors hover:bg-muted disabled:cursor-not-allowed disabled:opacity-40"
                        :disabled="page === totalPaginas"
                        aria-label="Página siguiente"
                        @click="page++"
                    >
                        <ChevronRight class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </section>

        <!-- Sección de análisis estadístico con pestañas interactivas. -->
        <section
            class="rounded-xl border border-sidebar-border/70 bg-card p-4 shadow-sm sm:p-5"
            aria-label="Análisis"
        >
            <!-- Navegación entre análisis y resumen de indicadores operativos. -->
            <div
                class="flex flex-col gap-4 border-b border-sidebar-border/70 pb-4 lg:flex-row lg:items-center lg:justify-between"
                role="tablist"
                aria-label="Categorías de análisis"
            >
                <div class="flex flex-wrap gap-1 rounded-lg bg-muted/50 p-1">
                    <button
                        v-for="t in tabs"
                        :key="t.key"
                        type="button"
                        role="tab"
                        :aria-selected="tab === t.key"
                        class="rounded-md px-3 py-2 text-sm transition-colors"
                        :class="
                            tab === t.key
                                ? 'bg-card font-semibold text-foreground shadow-sm'
                                : 'text-muted-foreground hover:text-foreground'
                        "
                        @click="tab = t.key"
                    >
                        {{ t.label }}
                    </button>
                </div>

                <!-- Indicadores complementarios de desempeño logístico. -->
                <p class="text-sm leading-relaxed text-muted-foreground">
                    Entrega promedio
                    <span class="font-semibold text-foreground">
                        {{ fmtDuracion(kpis.tiempoPromedioH) }} </span
                    >, fallos o devoluciones
                    <span class="font-semibold text-foreground">
                        {{ kpis.tasaFallo.toFixed(1) }}%
                    </span>
                </p>
            </div>

            <!-- Gráfico de tendencia diaria de envíos e incidencias. -->
            <div v-if="tab === 'tendencia'" class="pt-5">
                <div
                    class="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
                >
                    <p class="text-sm font-medium text-foreground">
                        Registros de los últimos 14 días
                    </p>

                    <!-- Leyenda para diferenciar envíos con y sin incidencias. -->
                    <div
                        class="flex flex-wrap items-center gap-4 text-xs text-muted-foreground"
                    >
                        <span class="flex items-center gap-2">
                            <span
                                class="h-2.5 w-2.5 rounded-sm"
                                :style="{ backgroundColor: colors.azulClaro }"
                            />
                            Sin incidencia
                        </span>

                        <span class="flex items-center gap-2">
                            <span
                                class="h-2.5 w-2.5 rounded-sm"
                                :style="{ backgroundColor: colors.rojo }"
                            />
                            Con incidencia
                        </span>
                    </div>
                </div>

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
                        <span class="text-xs font-medium text-muted-foreground">
                            {{ b.total || '' }}
                        </span>

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
                        >
                            {{ b.label }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Distribución de envíos según su nivel de prioridad. -->
            <div v-else-if="tab === 'prioridad'" class="pt-5">
                <div class="mb-4">
                    <h3 class="text-sm font-semibold text-foreground">
                        Envíos por prioridad
                    </h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Selecciona un nivel para filtrar el listado de envíos.
                    </p>
                </div>

                <ul class="flex flex-col gap-2">
                    <li v-for="p in porPrioridad" :key="p.prioridad">
                        <button
                            type="button"
                            class="flex w-full flex-wrap items-center gap-3 rounded-lg border px-3 py-3 text-left text-sm transition-colors sm:px-4"
                            :class="
                                filtros.prioridad === p.prioridad
                                    ? 'border-sidebar-border bg-muted/70'
                                    : 'border-transparent hover:border-sidebar-border/70 hover:bg-muted/40'
                            "
                            :aria-pressed="filtros.prioridad === p.prioridad"
                            @click="togglePrioridad(p.prioridad)"
                        >
                            <!-- Indicador visual del nivel de prioridad. -->
                            <span
                                class="h-2.5 w-2.5 shrink-0 rounded-full"
                                :style="{
                                    backgroundColor:
                                        prioridadInfo[p.prioridad].color,
                                }"
                            />
                            <span
                                class="min-w-24 flex-1 font-medium text-foreground"
                            >
                                {{ prioridadInfo[p.prioridad].label }}
                            </span>
                            <span
                                v-if="p.criticos"
                                class="text-xs font-medium"
                                :style="{ color: colors.rojo }"
                            >
                                {{ p.criticos }} con alerta
                            </span>
                            <span
                                class="text-right text-xs text-muted-foreground sm:w-24 sm:text-sm"
                            >
                                {{ p.total }} ({{ p.porcentaje.toFixed(0) }}%)
                            </span>
                        </button>
                    </li>
                </ul>
            </div>

            <!-- Comparación de destinos según volumen e incidencias. -->
            <div v-else class="pt-5">
                <div class="mb-4">
                    <h3 class="text-sm font-semibold text-foreground">
                        Rendimiento por destino
                    </h3>
                    <p class="mt-1 text-sm text-muted-foreground">
                        Los destinos con incidencias iguales o superiores al 30%
                        se destacan en rojo.
                    </p>
                </div>

                <ul class="flex flex-col gap-2">
                    <li v-for="d in topDestinos" :key="d.destino">
                        <button
                            type="button"
                            class="w-full rounded-lg border p-3 text-left transition-colors sm:p-4"
                            :class="
                                filtros.destino === d.destino
                                    ? 'border-sidebar-border bg-muted/70'
                                    : 'border-transparent hover:border-sidebar-border/70 hover:bg-muted/40'
                            "
                            :aria-pressed="filtros.destino === d.destino"
                            @click="toggleDestino(d.destino)"
                        >
                            <!-- Resumen del volumen y porcentaje de incidencias. -->
                            <div
                                class="mb-2 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"
                            >
                                <span
                                    class="text-sm font-medium text-foreground"
                                >
                                    {{ d.destino }}
                                </span>
                                <span
                                    class="text-xs text-muted-foreground sm:text-sm"
                                >
                                    {{ d.total }} envíos ·
                                    <span
                                        class="font-medium"
                                        :style="{
                                            color:
                                                d.pctIncidencias >= 30
                                                    ? colors.rojo
                                                    : undefined,
                                        }"
                                    >
                                        {{ d.pctIncidencias.toFixed(0) }}% con
                                        incidencia
                                    </span>
                                </span>
                            </div>

                            <!-- Barra proporcional al volumen del destino. -->
                            <div
                                class="h-2 overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    class="h-full rounded-full transition-all"
                                    :style="{
                                        width: `${d.ancho}%`,
                                        backgroundColor:
                                            d.pctIncidencias >= 30
                                                ? colors.rojo
                                                : colors.azulClaro,
                                    }"
                                />
                            </div>
                        </button>
                    </li>

                    <!-- Mensaje alternativo cuando no existen destinos disponibles. -->
                    <li
                        v-if="!topDestinos.length"
                        class="rounded-lg border border-dashed border-sidebar-border/70 px-4 py-8 text-center text-sm text-muted-foreground"
                    >
                        No hay datos disponibles con los filtros actuales.
                    </li>
                </ul>
            </div>
        </section>

        <!-- Panel lateral con la información detallada del envío seleccionado. -->
        <div
            v-if="seleccionado"
            class="fixed inset-0 z-50 flex justify-end bg-black/40 backdrop-blur-[2px]"
            @click.self="seleccionado = null"
        >
            <aside
                class="flex h-full w-full max-w-md flex-col overflow-y-auto border-l border-sidebar-border/70 bg-card shadow-xl"
                aria-label="Detalle del envío"
            >
                <!-- Encabezado con el código de guía y botón para cerrar. -->
                <div
                    class="flex items-start justify-between border-b border-sidebar-border/70 px-6 py-5"
                >
                    <div>
                        <p
                            class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            Detalle del envío
                        </p>
                        <h2
                            class="mt-1 font-mono text-xl font-semibold tracking-tight text-foreground"
                        >
                            {{ seleccionado.codigo_guia }}
                        </h2>
                    </div>

                    <button
                        type="button"
                        class="rounded-lg border border-sidebar-border/70 p-2 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                        aria-label="Cerrar detalle"
                        @click="seleccionado = null"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <div class="flex-1 p-6">
                    <!-- Estado, prioridad y situación actual del SLA. -->
                    <div
                        class="rounded-xl border border-sidebar-border/70 bg-muted/20 p-4"
                    >
                        <p
                            class="mb-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
                        >
                            Estado operativo
                        </p>

                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                class="inline-flex items-center gap-2 rounded-lg border border-sidebar-border/70 bg-card px-3 py-2 text-sm font-medium text-foreground"
                            >
                                <span
                                    class="h-2 w-2 rounded-full"
                                    :style="{
                                        backgroundColor:
                                            estadoInfo[seleccionado.estado]
                                                .color,
                                    }"
                                />
                                {{ estadoInfo[seleccionado.estado].label }}
                            </span>

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

                            <span
                                v-if="esAlerta(getSla(seleccionado, now))"
                                class="rounded-lg px-3 py-2 text-xs font-semibold"
                                :style="{
                                    color: slaInfo[getSla(seleccionado, now)]
                                        .color,
                                    backgroundColor: soft(
                                        slaInfo[getSla(seleccionado, now)]
                                            .color,
                                    ),
                                }"
                            >
                                {{ slaInfo[getSla(seleccionado, now)].label }}
                            </span>
                        </div>
                    </div>

                    <!-- Datos de las personas involucradas en el envío. -->
                    <div class="mt-6">
                        <h3 class="text-sm font-semibold text-foreground">
                            Información general
                        </h3>
                        <dl class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
                            <div
                                class="rounded-lg border border-sidebar-border/70 p-3"
                            >
                                <dt class="text-xs text-muted-foreground">
                                    Remitente
                                </dt>
                                <dd
                                    class="mt-1 text-sm font-medium break-words text-foreground"
                                >
                                    {{ seleccionado.remitente }}
                                </dd>
                            </div>

                            <div
                                class="rounded-lg border border-sidebar-border/70 p-3"
                            >
                                <dt class="text-xs text-muted-foreground">
                                    Destinatario
                                </dt>
                                <dd
                                    class="mt-1 text-sm font-medium break-words text-foreground"
                                >
                                    {{ seleccionado.destinatario }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Ubicación de origen y dirección de entrega. -->
                    <div class="mt-6">
                        <h3 class="text-sm font-semibold text-foreground">
                            Información logística
                        </h3>
                        <dl class="mt-3 grid grid-cols-2 gap-3">
                            <div
                                class="rounded-lg border border-sidebar-border/70 p-3"
                            >
                                <dt class="text-xs text-muted-foreground">
                                    Sucursal de origen
                                </dt>
                                <dd
                                    class="mt-1 text-sm break-words text-foreground"
                                >
                                    {{ seleccionado.sucursal_origen }}
                                </dd>
                            </div>

                            <div
                                class="rounded-lg border border-sidebar-border/70 p-3"
                            >
                                <dt class="text-xs text-muted-foreground">
                                    Destino
                                </dt>
                                <dd
                                    class="mt-1 text-sm break-words text-foreground"
                                >
                                    {{ seleccionado.territorio_destino }}
                                </dd>
                            </div>

                            <div
                                class="col-span-2 rounded-lg border border-sidebar-border/70 p-3"
                            >
                                <dt class="text-xs text-muted-foreground">
                                    Dirección de entrega
                                </dt>
                                <dd
                                    class="mt-1 text-sm leading-relaxed break-words text-foreground"
                                >
                                    {{ seleccionado.direccion_destino }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Características físicas y fechas importantes del envío. -->
                    <div class="mt-6">
                        <h3 class="text-sm font-semibold text-foreground">
                            Especificaciones y fechas
                        </h3>
                        <dl class="mt-3 grid grid-cols-2 gap-3">
                            <div
                                class="rounded-lg border border-sidebar-border/70 p-3"
                            >
                                <dt class="text-xs text-muted-foreground">
                                    Peso
                                </dt>
                                <dd
                                    class="mt-1 text-sm font-semibold text-foreground"
                                >
                                    {{ seleccionado.peso_kg.toFixed(2) }} kg
                                </dd>
                            </div>

                            <div
                                class="rounded-lg border border-sidebar-border/70 p-3"
                            >
                                <dt class="text-xs text-muted-foreground">
                                    Volumen
                                </dt>
                                <dd
                                    class="mt-1 text-sm font-semibold text-foreground"
                                >
                                    {{
                                        seleccionado.volumen_m3
                                            ? `${seleccionado.volumen_m3} m³`
                                            : '—'
                                    }}
                                </dd>
                            </div>

                            <div
                                class="rounded-lg border border-sidebar-border/70 p-3"
                            >
                                <dt class="text-xs text-muted-foreground">
                                    Fecha de registro
                                </dt>
                                <dd class="mt-1 text-sm text-foreground">
                                    {{ fmtFecha(seleccionado.fecha_registro) }}
                                </dd>
                            </div>

                            <div
                                class="rounded-lg border border-sidebar-border/70 p-3"
                            >
                                <dt class="text-xs text-muted-foreground">
                                    Fecha compromiso
                                </dt>
                                <dd class="mt-1 text-sm text-foreground">
                                    {{
                                        fmtFecha(
                                            seleccionado.fecha_comprometida,
                                        )
                                    }}
                                </dd>
                            </div>
                        </dl>
                    </div>

                    <!-- Línea de tiempo con los cambios registrados en el envío. -->
                    <div class="mt-8">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-foreground">
                                Historial de seguimiento
                            </h3>
                            <span
                                class="rounded-md bg-muted px-2 py-1 text-xs text-muted-foreground"
                            >
                                {{ seleccionado.eventos.length }} eventos
                            </span>
                        </div>
                        <ol v-if="seleccionado.eventos.length" class="mt-4">
                            <li
                                v-for="(ev, i) in seleccionado.eventos"
                                :key="ev.id_evento"
                                class="flex gap-3"
                            >
                                <!-- Indicadores y línea que conectan los eventos. -->
                                <div class="flex flex-col items-center">
                                    <span
                                        class="mt-1 h-3 w-3 shrink-0 rounded-full ring-4 ring-card"
                                        :style="{
                                            backgroundColor:
                                                estadoInfo[ev.estado].color,
                                        }"
                                    />
                                    <span
                                        v-if="
                                            i < seleccionado.eventos.length - 1
                                        "
                                        class="my-1 w-px flex-1 bg-border"
                                    />
                                </div>

                                <!-- Fecha, estado y observaciones de cada evento. -->
                                <div
                                    class="mb-5 min-w-0 flex-1 rounded-lg border border-sidebar-border/70 p-3"
                                >
                                    <p
                                        class="text-sm font-semibold text-foreground"
                                    >
                                        {{ estadoInfo[ev.estado].label }}
                                    </p>
                                    <p
                                        class="mt-1 text-xs text-muted-foreground"
                                    >
                                        {{ fmtFecha(ev.ocurrido_en) }}
                                    </p>
                                    <p
                                        v-if="ev.nota"
                                        class="mt-2 text-sm leading-relaxed text-muted-foreground"
                                    >
                                        {{ ev.nota }}
                                    </p>
                                </div>
                            </li>
                        </ol>

                        <!-- Mensaje cuando el envío aún no tiene eventos registrados. -->
                        <p
                            v-else
                            class="mt-4 rounded-lg border border-dashed border-sidebar-border/70 px-4 py-6 text-center text-sm text-muted-foreground"
                        >
                            No hay eventos registrados para este envío.
                        </p>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</template>
