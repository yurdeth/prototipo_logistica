<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { colors } from '@/UI/Colors';
import { dashboard } from '@/routes';

import {
    estadoInfo,
    prioridadInfo,
    slaInfo,
    type Envio,
} from '@/config/Envios/envios';
import { getSla, fmtFecha, fmtDuracion } from '@/composables/useEnviosBI';

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
import BiBadge from '@/components/bi/BiBadge.vue';
import BiStatus from '@/components/bi/BiStatus.vue';

import type {
    Activo,
    BarRow,
    Chip,
    Columna,
    Stat,
} from '@/components/bi/Utils';

/* ───────── Tipos de lo que envía EnvioController@index ───────── */
type Estado = Envio['estado'];
type Prioridad = Envio['prioridad'];

interface FilaEnvio {
    id_envio: number;
    codigo_guia: string;
    remitente: string;
    destinatario: string;
    territorio_destino: string;
    estado: Estado;
    prioridad: Prioridad;
    peso_kg: number;
    fecha_registro: string;
    fecha_comprometida: string;
    fecha_entrega: string | null;
}

interface Paginado<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
}

interface Filtros {
    q: string | null;
    estado: Estado | null;
    sucursal: number | null;
    prioridad: Prioridad | null;
    destino: number | null;
    desde: string | null;
    hasta: string | null;
    alerta: boolean;
}

interface Resumen {
    kpis: {
        total: number;
        peso_total: number;
        cerrados: number;
        a_tiempo: number;
        vencidos: number;
        en_riesgo: number;
        horas_promedio: number | null;
        fallos: number;
    };
    embudo: { estado: Estado; total: number }[];
    prioridad: { prioridad: Prioridad; total: number; criticos: number }[];
    destinos: { id: number; destino: string; total: number; incidencias: number }[];
    tendencia: { label: string; total: number; incidencias: number }[];
}

const props = defineProps<{
    envios: Paginado<FilaEnvio>;
    filtros: Filtros;
    catalogos: {
        estados: string[];
        sucursales: { id: number; nombre: string }[];
        territorios: { id: number; nombre: string }[];
    };
    resumen: Resumen;
    permisos: { registrar: boolean; editar: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Envíos', href: '#' },
        ],
    },
});

/* ───────── Consulta al servidor ───────── */
// Estado local de los filtros; se inicializa con lo que Laravel ya aplicó.
const f = reactive<Filtros>({ ...props.filtros });
const busqueda = ref(props.filtros.q ?? '');
const periodo = ref(0);

// Solo se envían los filtros que tienen valor, para mantener la URL limpia.
const parametros = (): Record<string, string | number> => {
    const params: Record<string, string | number> = {};

    if (busqueda.value.trim()) params.q = busqueda.value.trim();
    if (f.estado) params.estado = f.estado;
    if (f.sucursal) params.sucursal = f.sucursal;
    if (f.prioridad) params.prioridad = f.prioridad;
    if (f.destino) params.destino = f.destino;
    if (f.desde) params.desde = f.desde;
    if (f.hasta) params.hasta = f.hasta;
    if (f.alerta) params.alerta = 1;

    return params;
};

const consultar = (pagina = 1) => {
    const params = parametros();
    if (pagina > 1) params.page = pagina;

    router.get('/envios', params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

// Descarga un CSV con TODOS los envíos que cumplen los filtros actuales (no solo la página).
const exportar = () => {
    const qs = new URLSearchParams(
        Object.entries(parametros()).map(([k, v]) => [k, String(v)]),
    ).toString();

    window.location.href = `/envios/exportar${qs ? `?${qs}` : ''}`;
};

// La búsqueda espera a que se deje de escribir para no consultar en cada tecla.
let temporizador: ReturnType<typeof setTimeout> | undefined;
const alBuscar = (valor: string) => {
    busqueda.value = valor;
    clearTimeout(temporizador);
    temporizador = setTimeout(() => consultar(), 350);
};

const aFecha = (d: Date) =>
    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

const cambiarPeriodo = (dias: number) => {
    periodo.value = dias;
    f.hasta = null;
    f.desde = dias ? aFecha(new Date(Date.now() - dias * 86_400_000)) : null;
    consultar();
};

const cambiarRango = (campo: 'desde' | 'hasta', valor: string) => {
    f[campo] = valor || null;
    periodo.value = 0;
    consultar();
};

const alAlerta = (v: boolean) => {
    f.alerta = v;
    consultar();
};

const alEstado = (k: string) => {
    f.estado = f.estado === k ? null : (k as Estado);
    consultar();
};

const alPrioridad = (k: string) => {
    f.prioridad = f.prioridad === k ? null : (k as Prioridad);
    consultar();
};

const alDestino = (k: string | number) => {
    const id = Number(k);
    f.destino = f.destino === id ? null : id;
    consultar();
};

const alSucursal = (e: Event) => {
    const v = (e.target as HTMLSelectElement).value;
    f.sucursal = v ? Number(v) : null;
    consultar();
};

const page = computed({
    get: () => props.envios.current_page,
    set: (n: number) => consultar(n),
});

const hayFiltros = computed(
    () =>
        !!(
            busqueda.value ||
            f.estado ||
            f.sucursal ||
            f.prioridad ||
            f.destino ||
            f.desde ||
            f.hasta ||
            f.alerta
        ),
);

const limpiar = () => {
    clearTimeout(temporizador);
    busqueda.value = '';
    periodo.value = 0;
    Object.assign(f, {
        q: null,
        estado: null,
        sucursal: null,
        prioridad: null,
        destino: null,
        desde: null,
        hasta: null,
        alerta: false,
    });
    consultar();
};

/* ───────── Reloj para el SLA ───────── */
const now = ref(Date.now());
let reloj: ReturnType<typeof setInterval> | undefined;
onMounted(() => (reloj = setInterval(() => (now.value = Date.now()), 60_000)));
onBeforeUnmount(() => {
    clearInterval(reloj);
    clearTimeout(temporizador);
});

// getSla trabaja con la forma del envío; la fila del servidor trae los campos que usa.
const slaDe = (e: FilaEnvio) => getSla(e as unknown as Envio, now.value);
const esAlerta = (sla: string) => ['vencido', 'en_riesgo'].includes(sla);

/* ───────── Tarjetas de resumen (todo el conjunto filtrado) ───────── */
const k = computed(() => props.resumen.kpis);

const tarjetas = computed<Stat[]>(() => [
    {
        label: 'Envíos',
        value: k.value.total,
        hint: `${k.value.peso_total.toLocaleString('es-SV', { maximumFractionDigits: 0 })} kg transportados`,
    },
    {
        label: 'Cumplimiento de entrega',
        value: k.value.cerrados
            ? `${((k.value.a_tiempo / k.value.cerrados) * 100).toFixed(1)}%`
            : '—',
        hint: `${k.value.a_tiempo} de ${k.value.cerrados} cerrados a tiempo`,
    },
    {
        label: 'Requieren atención',
        value: k.value.en_riesgo + k.value.vencidos,
        hint: `${k.value.vencidos} vencidos y ${k.value.en_riesgo} por vencer en 24h`,
        color: k.value.vencidos > 0 ? colors.rojo : undefined,
    },
]);

/* ───────── Filtros visibles ───────── */
const periodos = [
    { value: 7, label: 'Últimos 7 días' },
    { value: 30, label: 'Últimos 30 días' },
    { value: 90, label: 'Últimos 90 días' },
    { value: 0, label: 'Todo el historial' },
] as const;

const chips = computed<Chip[]>(() =>
    props.resumen.embudo.map((e) => ({
        key: e.estado,
        label: estadoInfo[e.estado].label,
        count: e.total,
        color: estadoInfo[e.estado].color,
    })),
);

const activos = computed<Activo[]>(() => {
    const lista: Activo[] = [];

    if (f.estado) lista.push({ key: 'estado', label: `Estado: ${estadoInfo[f.estado].label}` });
    if (f.prioridad) lista.push({ key: 'prioridad', label: `Prioridad: ${prioridadInfo[f.prioridad].label}` });
    if (f.destino) {
        const t = props.catalogos.territorios.find((x) => x.id === f.destino);
        lista.push({ key: 'destino', label: `Destino: ${t?.nombre ?? f.destino}` });
    }
    if (f.sucursal) {
        const s = props.catalogos.sucursales.find((x) => x.id === f.sucursal);
        lista.push({ key: 'sucursal', label: `Sucursal: ${s?.nombre ?? f.sucursal}` });
    }
    if (f.desde || f.hasta) {
        lista.push({ key: 'fechas', label: `Fechas: ${f.desde ?? '…'} a ${f.hasta ?? '…'}` });
    }
    if (f.alerta) lista.push({ key: 'alerta', label: 'Solo con alerta' });

    return lista;
});

const quitarFiltro = (key: string) => {
    if (key === 'estado') f.estado = null;
    if (key === 'prioridad') f.prioridad = null;
    if (key === 'destino') f.destino = null;
    if (key === 'sucursal') f.sucursal = null;
    if (key === 'alerta') f.alerta = false;
    if (key === 'fechas') {
        f.desde = null;
        f.hasta = null;
        periodo.value = 0;
    }
    consultar();
};

/* ───────── Tabla ───────── */
const columnas: Columna[] = [
    { label: 'Envío' },
    { label: 'Destino' },
    { label: 'Estado' },
    { label: 'Compromiso' },
    { label: 'Situación' },
];

const abrir = (e: FilaEnvio) => router.visit(`/envios/${e.id_envio}`);

/* ───────── Análisis en pestañas ───────── */
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

const maxDia = computed(() => Math.max(1, ...props.resumen.tendencia.map((d) => d.total)));

const filasPrioridad = computed<BarRow[]>(() => {
    const suma = props.resumen.prioridad.reduce((a, p) => a + p.total, 0) || 1;

    return props.resumen.prioridad.map((p) => ({
        key: p.prioridad,
        label: prioridadInfo[p.prioridad].label,
        dot: prioridadInfo[p.prioridad].color,
        badge: p.criticos ? `${p.criticos} con alerta` : undefined,
        badgeColor: colors.rojo,
        detail: `${p.total} (${((p.total / suma) * 100).toFixed(0)}%)`,
    }));
});

const filasDestino = computed<BarRow[]>(() => {
    const maximo = Math.max(1, ...props.resumen.destinos.map((d) => d.total));

    return props.resumen.destinos.map((d) => {
        const pct = d.total ? (d.incidencias / d.total) * 100 : 0;

        return {
            key: String(d.id),
            label: d.destino,
            width: (d.total / maximo) * 100,
            color: pct >= 30 ? colors.rojo : colors.azulClaro,
            detail: `${d.total} envíos`,
            accent: `${pct.toFixed(0)}% con incidencia`,
            accentColor: pct >= 30 ? colors.rojo : undefined,
        };
    });
});

const tasaFallo = computed(() => (k.value.total ? (k.value.fallos / k.value.total) * 100 : 0));

const campoClase =
    'h-10 rounded-lg border border-sidebar-border/70 bg-card px-3 text-sm text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';
</script>

<template>
    <Head title="Envíos" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
        <EncabezadoAcciones
            title="Envíos"
            subtitle="Seguimiento de entregas y cumplimiento de compromisos"
            accion="Nuevo envío"
            href="/envios/crear"
            @exportar="exportar"
        />

        <StatCards :items="tarjetas" :cols="3" />

        <section
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm"
            aria-label="Listado de envíos"
        >
            <BiFilters
                :busqueda="busqueda"
                :periodo="periodo"
                :solo-alertas="f.alerta"
                placeholder="Buscar por guía, remitente o destinatario"
                :periodos="periodos"
                :activos="activos"
                :hay-filtros="hayFiltros"
                alerta-label="Solo con alerta"
                @update:busqueda="alBuscar"
                @update:periodo="cambiarPeriodo"
                @update:solo-alertas="alAlerta"
                @quitar="quitarFiltro"
                @limpiar="limpiar"
            >
                <!-- Misma fila que la búsqueda, el período y "Solo con alerta" -->
                <template #extra>
                    <select
                        :class="[campoClase, 'w-full sm:w-auto']"
                        aria-label="Sucursal de origen"
                        :value="f.sucursal ?? ''"
                        @change="alSucursal"
                    >
                        <option value="">Todas las sucursales</option>
                        <option
                            v-for="s in catalogos.sucursales"
                            :key="s.id"
                            :value="s.id"
                        >
                            {{ s.nombre }}
                        </option>
                    </select>

                    <label
                        class="flex items-center gap-2 text-sm text-muted-foreground"
                    >
                        Desde
                        <input
                            type="date"
                            :class="campoClase"
                            :value="f.desde ?? ''"
                            @change="cambiarRango('desde', ($event.target as HTMLInputElement).value)"
                        />
                    </label>

                    <label
                        class="flex items-center gap-2 text-sm text-muted-foreground"
                    >
                        Hasta
                        <input
                            type="date"
                            :class="campoClase"
                            :value="f.hasta ?? ''"
                            :min="f.desde ?? undefined"
                            @change="cambiarRango('hasta', ($event.target as HTMLInputElement).value)"
                        />
                    </label>
                </template>

                <BiChips :items="chips" :active="f.estado" @toggle="alEstado" />

            </BiFilters>

            <BiTable
                :columns="columnas"
                :vacio="!envios.data.length"
                empty-title="No se encontraron envíos"
            >
                <BiTableRow
                    v-for="e in envios.data"
                    :key="e.id_envio"
                    @select="abrir(e)"
                >
                    <td
                        class="px-5 py-4"
                        :style="
                            esAlerta(slaDe(e))
                                ? { boxShadow: `inset 3px 0 0 ${slaInfo[slaDe(e)].color}` }
                                : undefined
                        "
                    >
                        <p class="font-mono text-sm font-medium text-foreground">
                            {{ e.codigo_guia }}
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            {{ e.remitente }} a {{ e.destinatario }}
                        </p>
                    </td>

                    <td class="px-5 py-4 text-foreground">
                        {{ e.territorio_destino }}
                    </td>

                    <td class="px-5 py-4">
                        <BiStatus
                            class="text-foreground"
                            :color="estadoInfo[e.estado].color"
                            :label="estadoInfo[e.estado].label"
                        />
                    </td>

                    <td class="px-5 py-4 whitespace-nowrap text-muted-foreground">
                        {{ fmtFecha(e.fecha_comprometida) }}
                    </td>

                    <td class="px-5 py-4">
                        <BiBadge
                            v-if="esAlerta(slaDe(e))"
                            :color="slaInfo[slaDe(e)].color"
                            :label="slaInfo[slaDe(e)].label"
                        />
                        <span
                            v-else
                            class="text-sm whitespace-nowrap text-muted-foreground"
                            >{{ slaInfo[slaDe(e)].label }}</span
                        >
                    </td>
                </BiTableRow>
            </BiTable>

            <BiPagination
                v-model="page"
                :total="Math.max(1, envios.last_page)"
                :count="envios.total"
                unit="envíos"
            />
        </section>

        <section
            class="rounded-xl border border-sidebar-border/70 bg-card p-4 shadow-sm sm:p-5"
            aria-label="Análisis"
        >
            <BiTabs v-model="tab" :tabs="tabs">
                <p class="text-sm leading-relaxed text-muted-foreground">
                    Entrega promedio
                    <span class="font-semibold text-foreground">{{
                        fmtDuracion(k.horas_promedio)
                    }}</span
                    >, fallos o devoluciones
                    <span class="font-semibold text-foreground"
                        >{{ tasaFallo.toFixed(1) }}%</span
                    >
                </p>
            </BiTabs>

            <div v-if="tab === 'tendencia'" class="pt-5">
                <BiPanelHead title="Registros de los últimos 14 días">
                    <BiLegend :items="leyendaTendencia" />
                </BiPanelHead>

                <div
                    class="flex h-48 items-end justify-between gap-1.5 sm:gap-2"
                    role="img"
                    aria-label="Envíos registrados por día en los últimos 14 días"
                >
                    <div
                        v-for="(b, i) in resumen.tendencia"
                        :key="b.label"
                        class="flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-2"
                        :title="`${b.label}: ${b.total} envíos, ${b.incidencias} con incidencia`"
                    >
                        <span class="text-xs font-medium text-muted-foreground">{{
                            b.total || ''
                        }}</span>

                        <div
                            class="flex w-full max-w-8 flex-col-reverse overflow-hidden rounded-t-md"
                            :style="{ height: `${(b.total / maxDia) * 80}%` }"
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

            <div v-else-if="tab === 'prioridad'" class="pt-5">
                <BiPanelHead
                    title="Envíos por prioridad"
                    description="Selecciona un nivel para filtrar el listado de envíos."
                />
                <BiBarList
                    :rows="filasPrioridad"
                    :active="f.prioridad"
                    @select="alPrioridad"
                />
            </div>

            <div v-else class="pt-5">
                <BiPanelHead
                    title="Rendimiento por destino"
                    description="Los destinos con incidencias iguales o superiores al 30% se destacan en rojo."
                />
                <BiBarList
                    :rows="filasDestino"
                    :active="f.destino ? String(f.destino) : null"
                    empty="No hay datos disponibles con los filtros actuales."
                    @select="alDestino"
                />
            </div>
        </section>
    </div>
</template>
