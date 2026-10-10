<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { colors } from '@/UI/Colors';
import {
    Plus, Download, Search, X, AlertTriangle, ChevronLeft, ChevronRight,
    ChevronUp, ChevronDown, Package, CheckCircle2, Timer, Scale,
} from '@/UI/Icons';
import { dashboard } from '@/routes';
import {
    estadoInfo, prioridadInfo, slaInfo, generarEnviosDemo,
    type Envio,
} 

from '@/config/envios';
import { useEnviosBI, getSla, fmtFecha, fmtDuracion, type SortKey } from '@/composables/useEnviosBI';

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

const {
    now, filtros, sort, page, filtrados, kpis, embudo, tendencia, porPrioridad, topDestinos,
    pagina, totalPaginas, ordenarPor, toggleEstado, togglePrioridad, toggleDestino,
    hayFiltros, limpiar, exportarCsv,
} = useEnviosBI(computed(() => props.envios));

const soft = (hex: string, alpha = '1A') => `${hex}${alpha}`;

// Detalle (panel lateral)
const seleccionado = ref<Envio | null>(null);

const columnas: { key: SortKey; label: string; clase?: string }[] = [
    { key: 'peso_kg', label: 'Peso', clase: 'hidden md:table-cell' },
    { key: 'fecha_comprometida', label: 'Compromiso' },
];

const periodos = [
    { value: 7, label: 'Últimos 7 días' },
    { value: 30, label: 'Últimos 30 días' },
    { value: 90, label: 'Últimos 90 días' },
    { value: 0, label: 'Todo el historial' },
] as const;

const inputClase =
    'h-9 rounded-lg border border-sidebar-border/70 bg-card px-3 text-sm text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';
</script>

<template>
    <Head title="Envíos" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
        <!-- Encabezado -->
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-foreground">Envíos</h1>
                <p class="text-sm text-muted-foreground">Seguimiento y cumplimiento de compromisos de entrega</p>
            </div>
            <div class="flex gap-2">
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-sidebar-border/70 bg-card px-3.5 py-2 text-sm font-medium text-foreground hover:bg-muted"
                    @click="exportarCsv"
                >
                    <Download class="h-4 w-4" aria-hidden="true" />
                    Exportar CSV
                </button>
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-lg px-3.5 py-2 text-sm font-medium text-white hover:opacity-90"
                    :style="{ backgroundColor: colors.azulClaro }"
                >
                    <Plus class="h-4 w-4" aria-hidden="true" />
                    Nuevo envío
                </button>
            </div>
        </div>

        <!-- Filtros -->
        <div class="flex flex-wrap items-center gap-2">
            <div class="relative min-w-56 flex-1 sm:max-w-xs">
                <Search class="absolute top-2.5 left-3 h-4 w-4 text-muted-foreground" aria-hidden="true" />
                <input
                    v-model="filtros.busqueda"
                    type="search"
                    placeholder="Guía, cliente o destino…"
                    aria-label="Buscar envíos"
                    :class="[inputClase, 'w-full pl-9']"
                />
            </div>

            <select v-model.number="filtros.periodo" aria-label="Período" :class="inputClase">
                <option v-for="p in periodos" :key="p.value" :value="p.value">{{ p.label }}</option>
            </select>

            <button
                type="button"
                class="inline-flex h-9 items-center gap-1.5 rounded-lg border px-3 text-sm font-medium transition-colors"
                :class="filtros.soloRiesgo ? '' : 'border-sidebar-border/70 bg-card text-foreground hover:bg-muted'"
                :style="filtros.soloRiesgo ? { color: colors.naranjaClaro, borderColor: colors.naranjaClaro, backgroundColor: soft(colors.naranjaClaro) } : undefined"
                :aria-pressed="filtros.soloRiesgo"
                @click="filtros.soloRiesgo = !filtros.soloRiesgo"
            >
                <AlertTriangle class="h-4 w-4" aria-hidden="true" />
                En riesgo o vencidos
            </button>

            <!-- Filtros activos (clic en las gráficas) -->
            <template v-if="hayFiltros">
                <span
                    v-if="filtros.estado"
                    class="inline-flex h-9 items-center gap-1.5 rounded-lg px-3 text-xs font-medium"
                    :style="{ color: estadoInfo[filtros.estado].color, backgroundColor: soft(estadoInfo[filtros.estado].color) }"
                >
                    {{ estadoInfo[filtros.estado].label }}
                    <button type="button" aria-label="Quitar filtro de estado" @click="filtros.estado = null"><X class="h-3.5 w-3.5" /></button>
                </span>
                <span
                    v-if="filtros.prioridad"
                    class="inline-flex h-9 items-center gap-1.5 rounded-lg px-3 text-xs font-medium"
                    :style="{ color: prioridadInfo[filtros.prioridad].color, backgroundColor: soft(prioridadInfo[filtros.prioridad].color) }"
                >
                    Prioridad {{ prioridadInfo[filtros.prioridad].label }}
                    <button type="button" aria-label="Quitar filtro de prioridad" @click="filtros.prioridad = null"><X class="h-3.5 w-3.5" /></button>
                </span>
                <span
                    v-if="filtros.destino"
                    class="inline-flex h-9 items-center gap-1.5 rounded-lg px-3 text-xs font-medium"
                    :style="{ color: colors.azulClaro, backgroundColor: soft(colors.azulClaro) }"
                >
                    {{ filtros.destino }}
                    <button type="button" aria-label="Quitar filtro de destino" @click="filtros.destino = null"><X class="h-3.5 w-3.5" /></button>
                </span>
                <button type="button" class="h-9 px-2 text-sm hover:underline" :style="{ color: colors.azulClaro }" @click="limpiar">
                    Limpiar filtros
                </button>
            </template>
        </div>

        <!-- KPIs (se recalculan con los filtros) -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="rounded-xl border border-sidebar-border/70 bg-card p-4" :style="{ borderLeft: `4px solid ${colors.azulClaro}` }">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-muted-foreground">Envíos</span>
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg" :style="{ backgroundColor: soft(colors.azulClaro), color: colors.azulClaro }">
                        <Package class="h-4 w-4" aria-hidden="true" />
                    </span>
                </div>
                <p class="mt-2 text-3xl font-semibold text-foreground">{{ kpis.total }}</p>
                <p class="mt-1 flex items-center gap-1 text-xs text-muted-foreground">
                    <Scale class="h-3.5 w-3.5" aria-hidden="true" />
                    {{ kpis.pesoTotal.toLocaleString('es-SV', { maximumFractionDigits: 0 }) }} kg transportados
                </p>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 bg-card p-4" :style="{ borderLeft: `4px solid ${colors.verde}` }">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-muted-foreground">Cumplimiento de entrega</span>
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg" :style="{ backgroundColor: soft(colors.verde), color: colors.verde }">
                        <CheckCircle2 class="h-4 w-4" aria-hidden="true" />
                    </span>
                </div>
                <p class="mt-2 text-3xl font-semibold text-foreground">
                    {{ kpis.cumplimiento === null ? '—' : `${kpis.cumplimiento.toFixed(1)}%` }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">{{ kpis.aTiempo }} de {{ kpis.cerrados }} cerrados a tiempo</p>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 bg-card p-4" :style="{ borderLeft: `4px solid ${colors.naranjaClaro}` }">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-muted-foreground">Requieren atención</span>
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg" :style="{ backgroundColor: soft(colors.naranjaClaro), color: colors.naranjaClaro }">
                        <AlertTriangle class="h-4 w-4" aria-hidden="true" />
                    </span>
                </div>
                <p class="mt-2 text-3xl font-semibold text-foreground">{{ kpis.enRiesgo + kpis.vencidos }}</p>
                <p class="mt-1 text-xs text-muted-foreground">
                    <span :style="{ color: colors.rojo }">{{ kpis.vencidos }} vencidos</span> · {{ kpis.enRiesgo }} vencen en 24 h
                </p>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 bg-card p-4" :style="{ borderLeft: `4px solid ${colors.cian}` }">
                <div class="flex items-center justify-between">
                    <span class="text-xs text-muted-foreground">Tiempo promedio de entrega</span>
                    <span class="flex h-8 w-8 items-center justify-center rounded-lg" :style="{ backgroundColor: soft(colors.cian), color: colors.cian }">
                        <Timer class="h-4 w-4" aria-hidden="true" />
                    </span>
                </div>
                <p class="mt-2 text-3xl font-semibold text-foreground">{{ fmtDuracion(kpis.tiempoPromedioH) }}</p>
                <p class="mt-1 text-xs text-muted-foreground">Tasa de fallo/devolución: {{ kpis.tasaFallo.toFixed(1) }}%</p>
            </div>
        </div>

        <!-- Embudo + tendencia -->
        <div class="grid gap-4 lg:grid-cols-3">
            <div class="rounded-xl border border-sidebar-border/70 bg-card p-5">
                <h2 class="text-base font-medium text-foreground">Embudo por estado</h2>
                <p class="text-xs text-muted-foreground">Haz clic en un estado para filtrar la tabla</p>

                <ul class="mt-4 flex flex-col gap-1.5">
                    <li v-for="fila in embudo" :key="fila.estado">
                        <button
                            type="button"
                            class="group w-full rounded-lg px-2 py-1.5 text-left transition-colors hover:bg-muted"
                            :class="filtros.estado === fila.estado ? 'bg-muted' : ''"
                            :aria-pressed="filtros.estado === fila.estado"
                            @click="toggleEstado(fila.estado)"
                        >
                            <div class="mb-1 flex items-center justify-between text-xs">
                                <span class="text-foreground">{{ estadoInfo[fila.estado].label }}</span>
                                <span class="font-mono text-muted-foreground">{{ fila.total }}</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-muted">
                                <div
                                    class="h-full rounded-full transition-all"
                                    :style="{ width: `${fila.ancho}%`, backgroundColor: estadoInfo[fila.estado].color }"
                                />
                            </div>
                        </button>
                    </li>
                </ul>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 bg-card p-5 lg:col-span-2">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h2 class="text-base font-medium text-foreground">Registros por día</h2>
                        <p class="text-xs text-muted-foreground">Últimos 14 días, con la parte que terminó con incidencia</p>
                    </div>
                    <div class="flex gap-4 text-xs text-muted-foreground">
                        <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-sm" :style="{ backgroundColor: colors.azulClaro }" />Sin incidencia</span>
                        <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-sm" :style="{ backgroundColor: colors.rojo }" />Con incidencia</span>
                    </div>
                </div>

                <div class="mt-6 flex h-48 items-end justify-between gap-1.5" role="img" aria-label="Envíos registrados por día en los últimos 14 días">
                    <div
                        v-for="(b, i) in tendencia.buckets"
                        :key="b.label"
                        class="flex h-full flex-1 flex-col items-center justify-end gap-1"
                        :title="`${b.label}: ${b.total} envíos, ${b.incidencias} con incidencia`"
                    >
                        <span class="text-[10px] text-muted-foreground">{{ b.total || '' }}</span>
                        <div class="flex w-full max-w-6 flex-col-reverse overflow-hidden rounded-t" :style="{ height: `${(b.total / tendencia.max) * 80}%` }">
                            <div :style="{ height: `${b.total ? ((b.total - b.incidencias) / b.total) * 100 : 0}%`, backgroundColor: colors.azulClaro }" />
                            <div :style="{ height: `${b.total ? (b.incidencias / b.total) * 100 : 0}%`, backgroundColor: colors.rojo }" />
                        </div>
                        <span class="text-[10px] text-muted-foreground" :class="i % 2 ? 'invisible sm:visible' : ''">{{ b.label }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Prioridad + destinos -->
        <div class="grid gap-4 lg:grid-cols-2">
            <div class="rounded-xl border border-sidebar-border/70 bg-card p-5">
                <h2 class="text-base font-medium text-foreground">Por prioridad</h2>
                <p class="text-xs text-muted-foreground">Cuántos de cada nivel están en riesgo o vencidos</p>

                <div class="mt-4 flex h-3 overflow-hidden rounded-full bg-muted" role="img" aria-label="Distribución por prioridad">
                    <div
                        v-for="p in porPrioridad"
                        :key="p.prioridad"
                        class="transition-all"
                        :style="{ width: `${p.porcentaje}%`, backgroundColor: prioridadInfo[p.prioridad].color }"
                    />
                </div>

                <ul class="mt-4 flex flex-col gap-1">
                    <li v-for="p in porPrioridad" :key="p.prioridad">
                        <button
                            type="button"
                            class="flex w-full items-center gap-2 rounded-lg px-2 py-2 text-left text-sm transition-colors hover:bg-muted"
                            :class="filtros.prioridad === p.prioridad ? 'bg-muted' : ''"
                            :aria-pressed="filtros.prioridad === p.prioridad"
                            @click="togglePrioridad(p.prioridad)"
                        >
                            <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: prioridadInfo[p.prioridad].color }" />
                            <span class="flex-1 text-foreground">{{ prioridadInfo[p.prioridad].label }}</span>
                            <span v-if="p.criticos" class="rounded-full px-2 py-0.5 text-xs font-medium" :style="{ color: colors.rojo, backgroundColor: soft(colors.rojo) }">
                                {{ p.criticos }} críticos
                            </span>
                            <span class="w-16 text-right font-mono text-xs text-muted-foreground">{{ p.total }} · {{ p.porcentaje.toFixed(0) }}%</span>
                        </button>
                    </li>
                </ul>
            </div>

            <div class="rounded-xl border border-sidebar-border/70 bg-card p-5">
                <h2 class="text-base font-medium text-foreground">Principales destinos</h2>
                <p class="text-xs text-muted-foreground">Volumen y porcentaje con incidencia (vencidos, tardíos o no entregados)</p>

                <ul class="mt-4 flex flex-col gap-1">
                    <li v-for="d in topDestinos" :key="d.destino">
                        <button
                            type="button"
                            class="w-full rounded-lg px-2 py-1.5 text-left transition-colors hover:bg-muted"
                            :class="filtros.destino === d.destino ? 'bg-muted' : ''"
                            :aria-pressed="filtros.destino === d.destino"
                            @click="toggleDestino(d.destino)"
                        >
                            <div class="mb-1 flex items-center justify-between text-xs">
                                <span class="text-foreground">{{ d.destino }}</span>
                                <span class="font-mono text-muted-foreground">
                                    {{ d.total }} ·
                                    <span :style="{ color: d.pctIncidencias >= 30 ? colors.rojo : undefined }">{{ d.pctIncidencias.toFixed(0) }}% inc.</span>
                                </span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-muted">
                                <div class="h-full rounded-full transition-all" :style="{ width: `${d.ancho}%`, backgroundColor: d.pctIncidencias >= 30 ? colors.rojo : colors.azulClaro }" />
                            </div>
                        </button>
                    </li>
                    <li v-if="!topDestinos.length" class="px-2 py-4 text-sm text-muted-foreground">Sin datos con los filtros actuales.</li>
                </ul>
            </div>
        </div>

        <!-- Tabla -->
        <div class="rounded-xl border border-sidebar-border/70 bg-card">
            <div class="flex items-center justify-between px-5 pt-5">
                <h2 class="text-base font-medium text-foreground">Listado de envíos</h2>
                <span class="text-xs text-muted-foreground">{{ filtrados.length }} resultados</span>
            </div>

            <div class="overflow-x-auto">
                <table class="mt-3 w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-sidebar-border/70 font-mono text-[10px] tracking-wider text-muted-foreground uppercase">
                            <th class="px-5 py-2 font-normal">Guía</th>
                            <th class="hidden px-5 py-2 font-normal lg:table-cell">Remitente → Destinatario</th>
                            <th class="px-5 py-2 font-normal">Destino</th>
                            <th class="px-5 py-2 font-normal">Estado</th>
                            <th class="hidden px-5 py-2 font-normal sm:table-cell">Prioridad</th>
                            <th v-for="c in columnas" :key="c.key" class="px-5 py-2 font-normal" :class="c.clase">
                                <button type="button" class="inline-flex items-center gap-1 uppercase hover:text-foreground" @click="ordenarPor(c.key)">
                                    {{ c.label }}
                                    <ChevronUp v-if="sort.key === c.key && sort.dir === 'asc'" class="h-3 w-3" />
                                    <ChevronDown v-else-if="sort.key === c.key" class="h-3 w-3" />
                                </button>
                            </th>
                            <th class="px-5 py-2 font-normal">SLA</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="e in pagina"
                            :key="e.id_envio"
                            class="cursor-pointer border-b border-sidebar-border/40 last:border-b-0 hover:bg-muted/50"
                            tabindex="0"
                            @click="seleccionado = e"
                            @keydown.enter="seleccionado = e"
                        >
                            <td class="px-5 py-3 font-mono text-xs text-foreground">{{ e.codigo_guia }}</td>
                            <td class="hidden px-5 py-3 text-muted-foreground lg:table-cell">{{ e.remitente }} → {{ e.destinatario }}</td>
                            <td class="px-5 py-3 text-foreground">{{ e.territorio_destino }}</td>
                            <td class="px-5 py-3">
                                <span class="rounded-full px-2.5 py-1 text-xs font-medium" :style="{ color: estadoInfo[e.estado].color, backgroundColor: soft(estadoInfo[e.estado].color) }">
                                    {{ estadoInfo[e.estado].label }}
                                </span>
                            </td>
                            <td class="hidden px-5 py-3 sm:table-cell">
                                <span class="text-xs font-medium" :style="{ color: prioridadInfo[e.prioridad].color }">● {{ prioridadInfo[e.prioridad].label }}</span>
                            </td>
                            <td class="hidden px-5 py-3 text-muted-foreground md:table-cell">{{ e.peso_kg.toFixed(1) }} kg</td>
                            <td class="px-5 py-3 text-muted-foreground">{{ fmtFecha(e.fecha_comprometida) }}</td>
                            <td class="px-5 py-3">
                                <span class="rounded-full px-2.5 py-1 text-xs font-medium whitespace-nowrap" :style="{ color: slaInfo[getSla(e, now)].color, backgroundColor: soft(slaInfo[getSla(e, now)].color) }">
                                    {{ slaInfo[getSla(e, now)].label }}
                                </span>
                            </td>
                        </tr>
                        <tr v-if="!pagina.length">
                            <td colspan="8" class="px-5 py-10 text-center text-sm text-muted-foreground">No hay envíos con los filtros actuales.</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Paginación -->
            <div class="flex items-center justify-between border-t border-sidebar-border/70 px-5 py-3 text-sm text-muted-foreground">
                <span>Página {{ page }} de {{ totalPaginas }}</span>
                <div class="flex gap-1">
                    <button type="button" class="rounded-lg border border-sidebar-border/70 p-1.5 hover:bg-muted disabled:opacity-40" :disabled="page === 1" aria-label="Página anterior" @click="page--">
                        <ChevronLeft class="h-4 w-4" />
                    </button>
                    <button type="button" class="rounded-lg border border-sidebar-border/70 p-1.5 hover:bg-muted disabled:opacity-40" :disabled="page === totalPaginas" aria-label="Página siguiente" @click="page++">
                        <ChevronRight class="h-4 w-4" />
                    </button>
                </div>
            </div>
        </div>

        <!-- Panel de detalle -->
        <div v-if="seleccionado" class="fixed inset-0 z-50 flex justify-end bg-black/40" @click.self="seleccionado = null">
            <aside class="flex h-full w-full max-w-md flex-col overflow-y-auto border-l border-sidebar-border/70 bg-card p-6 shadow-xl" aria-label="Detalle del envío">
                <div class="flex items-start justify-between">
                    <div>
                        <p class="font-mono text-xs text-muted-foreground">GUÍA</p>
                        <h2 class="text-xl font-semibold text-foreground">{{ seleccionado.codigo_guia }}</h2>
                    </div>
                    <button type="button" class="rounded-lg p-1.5 text-muted-foreground hover:bg-muted" aria-label="Cerrar detalle" @click="seleccionado = null">
                        <X class="h-5 w-5" />
                    </button>
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    <span class="rounded-full px-2.5 py-1 text-xs font-medium" :style="{ color: estadoInfo[seleccionado.estado].color, backgroundColor: soft(estadoInfo[seleccionado.estado].color) }">
                        {{ estadoInfo[seleccionado.estado].label }}
                    </span>
                    <span class="rounded-full px-2.5 py-1 text-xs font-medium" :style="{ color: slaInfo[getSla(seleccionado, now)].color, backgroundColor: soft(slaInfo[getSla(seleccionado, now)].color) }">
                        {{ slaInfo[getSla(seleccionado, now)].label }}
                    </span>
                    <span class="rounded-full px-2.5 py-1 text-xs font-medium" :style="{ color: prioridadInfo[seleccionado.prioridad].color, backgroundColor: soft(prioridadInfo[seleccionado.prioridad].color) }">
                        {{ prioridadInfo[seleccionado.prioridad].label }}
                    </span>
                </div>

                <dl class="mt-6 grid grid-cols-2 gap-x-4 gap-y-4 text-sm">
                    <div><dt class="text-xs text-muted-foreground">Remitente</dt><dd class="text-foreground">{{ seleccionado.remitente }}</dd></div>
                    <div><dt class="text-xs text-muted-foreground">Destinatario</dt><dd class="text-foreground">{{ seleccionado.destinatario }}</dd></div>
                    <div><dt class="text-xs text-muted-foreground">Sucursal de origen</dt><dd class="text-foreground">{{ seleccionado.sucursal_origen }}</dd></div>
                    <div><dt class="text-xs text-muted-foreground">Destino</dt><dd class="text-foreground">{{ seleccionado.territorio_destino }}</dd></div>
                    <div class="col-span-2"><dt class="text-xs text-muted-foreground">Dirección</dt><dd class="text-foreground">{{ seleccionado.direccion_destino }}</dd></div>
                    <div><dt class="text-xs text-muted-foreground">Peso</dt><dd class="text-foreground">{{ seleccionado.peso_kg.toFixed(2) }} kg</dd></div>
                    <div><dt class="text-xs text-muted-foreground">Volumen</dt><dd class="text-foreground">{{ seleccionado.volumen_m3 ? `${seleccionado.volumen_m3} m³` : '—' }}</dd></div>
                    <div><dt class="text-xs text-muted-foreground">Registrado</dt><dd class="text-foreground">{{ fmtFecha(seleccionado.fecha_registro) }}</dd></div>
                    <div><dt class="text-xs text-muted-foreground">Compromiso</dt><dd class="text-foreground">{{ fmtFecha(seleccionado.fecha_comprometida) }}</dd></div>
                </dl>

                <h3 class="mt-8 text-sm font-medium text-foreground">Historial de eventos</h3>
                <ol class="mt-3">
                    <li v-for="(ev, i) in seleccionado.eventos" :key="ev.id_evento" class="flex gap-3">
                        <div class="flex flex-col items-center">
                            <span class="mt-1 h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: estadoInfo[ev.estado].color }" />
                            <span v-if="i < seleccionado.eventos.length - 1" class="my-1 w-px flex-1 bg-border" />
                        </div>
                        <div class="pb-4">
                            <p class="text-sm font-medium text-foreground">{{ estadoInfo[ev.estado].label }}</p>
                            <p class="text-xs text-muted-foreground">{{ fmtFecha(ev.ocurrido_en) }}</p>
                            <p v-if="ev.nota" class="mt-0.5 text-xs text-muted-foreground">{{ ev.nota }}</p>
                        </div>
                    </li>
                </ol>
            </aside>
        </div>
    </div>
</template>