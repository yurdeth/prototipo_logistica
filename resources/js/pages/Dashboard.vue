<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { colors } from '@/UI/Colors';
import {
    AlertTriangle,
    CheckCircle2,
    Clock,
    LayoutDashboard,
    BarChart3,
} from '@/UI/Icons';
import { dashboard } from '@/routes';
import { estadoInfo, slaInfo, type Envio } from '@/config/Envios/envios';
import { flowSteps } from '@/config/dashboard';
import { fmtFecha } from '@/composables/useEnviosBI';

// Componentes BI reutilizables (los mismos de Envíos y Rutas).
import StatCards from '@/components/bi/StatCards.vue';
import BiTabs from '@/components/bi/BiTabs.vue';
import BiPanelHead from '@/components/bi/BiPanelHead.vue';
import BiTable from '@/components/bi/BiTable.vue';
import BiTableRow from '@/components/bi/BiTableRow.vue';
import BiBarList from '@/components/bi/BiBarList.vue';
import BiBadge from '@/components/bi/BiBadge.vue';
import {
    soft,
    type BarRow,
    type Columna,
    type Stat,
} from '@/components/bi/Utils';

type Estado = Envio['estado'];
type TipoIncidencia = 'VENCIDO' | 'FALLO' | 'EN_RIESGO';

interface Ventana {
    total: number;
    cerrados: number;
    a_tiempo: number;
    horas: number | null;
    incidencias: number;
}

interface Incidencia {
    id_envio: number;
    codigo_guia: string;
    cliente: string;
    destino: string;
    estado: Estado;
    fecha_comprometida: string;
    tipo: TipoIncidencia;
    horas: number;
}

// Datos reales enviados por DashboardController.
const props = defineProps<{
    kpis: { actual: Ventana; previo: Ventana };
    alertas: { vencidos: number; en_riesgo: number; fallos: number };
    incidencias: Incidencia[];
    mensual: { label: string; total: number }[];
    sucursales: {
        sucursal: string;
        total: number;
        cerrados: number;
        a_tiempo: number;
    }[];
    estados: { estado: Estado; total: number }[];
    recientes: {
        id_envio: number;
        codigo_guia: string;
        destino: string;
        cliente: string;
        estado: Estado;
        fecha_comprometida: string;
    }[];

    permisos: { registrar: boolean };
    eficienciaRutas: { codigo: string; pct: number }[];
    resumenOperativo: {
        distancia_km: number | null;
        tiempo_horas: number | null;
        combustible_litros: number | null;
        co2_kg: number | null;
    };
}>();

defineOptions({
    layout: { breadcrumbs: [{ title: 'Dashboard', href: dashboard() }] },
});

//  Encabezado y botones Operación / Analítica BI
type Modo = 'operacion' | 'bi';
const modo = ref<Modo>('operacion');
const modos: { key: Modo; label: string; icon: typeof LayoutDashboard }[] = [
    { key: 'operacion', label: 'Operación', icon: LayoutDashboard },
    { key: 'bi', label: 'Analítica BI', icon: BarChart3 },
];

const page = usePage();
const nombre = computed(() => {
    const auth = page.props.auth as unknown as
        | { user?: { name?: string } }
        | undefined;
    return auth?.user?.name?.split(' ')[0] ?? '';
});

const hoy = new Date().toLocaleDateString('es-SV', {
    weekday: 'long',
    day: 'numeric',
    month: 'long',
});
const hoyTexto = hoy.charAt(0).toUpperCase() + hoy.slice(1);

// Tarjetas de resumen: últimos 30 días frente a los 30 anteriores
interface OpcionesDelta {
    relativo?: boolean;
    mejor?: 'alto' | 'bajo';
}

const delta = (
    actual: number | null,
    previo: number | null,
    { relativo = false, mejor }: OpcionesDelta = {},
) => {
    if (actual === null || previo === null || (relativo && previo === 0)) {
        return { texto: 'Sin datos previos', empeoro: false };
    }

    const dif = relativo ? ((actual - previo) / previo) * 100 : actual - previo;

    if (Math.abs(dif) < 0.05) {
        return { texto: 'Sin cambios vs. 30 días previos', empeoro: false };
    }

    return {
        texto: `${dif > 0 ? '+' : ''}${dif.toFixed(1)}${relativo ? '%' : ' pp'} vs. 30 días previos`,
        empeoro: (mejor === 'alto' && dif < 0) || (mejor === 'bajo' && dif > 0),
    };
};

const porcentaje = (parte: number, todo: number) =>
    todo ? (parte / todo) * 100 : null;
const cumplimiento = (v: Ventana) => porcentaje(v.a_tiempo, v.cerrados);
const tasaIncidencia = (v: Ventana) => porcentaje(v.incidencias, v.total);
const fmtPct = (v: number | null) => (v === null ? '—' : `${v.toFixed(1)}%`);
const fmtHoras = (h: number | null) => {
    if (h === null) return '—';
    return h < 48 ? `${h.toFixed(1)} h` : `${(h / 24).toFixed(1)} días`;
};

// El color solo aparece cuando un indicador empeoró.
const resumen = computed<Stat[]>(() => {
    const { actual: a, previo: p } = props.kpis;
    const volumen = delta(a.total, p.total, { relativo: true });
    const cump = delta(cumplimiento(a), cumplimiento(p), { mejor: 'alto' });
    const horas = delta(a.horas, p.horas, { relativo: true, mejor: 'bajo' });
    const incid = delta(tasaIncidencia(a), tasaIncidencia(p), {
        mejor: 'bajo',
    });
    return [
        {
            label: 'Envíos (30 días)',
            value: a.total.toLocaleString('es-SV'),
            hint: volumen.texto,
            accentColor: colors.azulClaro,
        },
        {
            label: 'Cumplimiento de entrega',
            value: fmtPct(cumplimiento(a)),
            hint: cump.texto,
            color: cump.empeoro ? colors.rojo : undefined,
            accentColor: colors.verde,
        },
        {
            label: 'Entrega promedio',
            value: fmtHoras(a.horas),
            hint: horas.texto,
            color: horas.empeoro ? colors.rojo : undefined,
            accentColor: colors.cian,
        },
        {
            label: 'Envíos con incidencia',
            value: fmtPct(tasaIncidencia(a)),
            hint: incid.texto,
            color: incid.empeoro ? colors.rojo : undefined,
            accentColor: colors.naranjaClaro,
        },
    ];
});

/* ───────── Estado de las entregas (dona con 4 grupos) ───────── */
const RADIO = 48;
const CIRCUNFERENCIA = 2 * Math.PI * RADIO;

const sumar = (claves: Estado[]) =>
    props.estados
        .filter((e) => claves.includes(e.estado))
        .reduce((a, e) => a + e.total, 0);

const grupos = computed(() => [
    {
        label: 'Entregadas',
        total: sumar(['ENTREGADO']),
        color: estadoInfo.ENTREGADO.color,
    },
    {
        label: 'En tránsito',
        total: sumar(['RECOLECTADO', 'EN_TRANSITO', 'EN_CENTRO', 'EN_RUTA']),
        color: estadoInfo.EN_RUTA.color,
    },
    {
        label: 'Pendientes',
        total: sumar(['REGISTRADO']),
        color: estadoInfo.REGISTRADO.color,
    },
    {
        label: 'Fallidas',
        total: sumar(['FALLIDO', 'DEVUELTO']),
        color: estadoInfo.FALLIDO.color,
    },
]);

const totalEnvios = computed(() =>
    grupos.value.reduce((a, g) => a + g.total, 0),
);
const pctGrupo = (n: number) =>
    totalEnvios.value ? ((n / totalEnvios.value) * 100).toFixed(1) : '0.0';

// Cada grupo es un arco del círculo: largo proporcional y desfase acumulado.
const segmentos = computed(() => {
    let acumulado = 0;
    return grupos.value
        .filter((g) => g.total > 0)
        .map((g) => {
            const largo = (g.total / totalEnvios.value) * CIRCUNFERENCIA;
            const segmento = {
                label: g.label,
                color: g.color,
                largo,
                desfase: -acumulado,
            };

            acumulado += largo;
            return segmento;
        });
});

/* ───────── Datos de rutas (eficiencia y resumen operativo) ───────── */
const colorEficiencia = (p: number) =>
    p >= 90 ? colors.azulClaro : p >= 75 ? colors.amarillo : colors.rojo;

const eficiencia = computed(() => props.eficienciaRutas);

const formatoIndicador = (valor: number | null) =>
    valor === null
        ? '—'
        : valor.toLocaleString('es-SV', {
              minimumFractionDigits: 1,
              maximumFractionDigits: 1,
          });

// Íconos dibujados con trazos (no dependen de @/UI/Icons).

const iconos = {
    distancia: [
        'M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z',
        'M15 10a3 3 0 1 1-6 0a3 3 0 0 1 6 0Z',
    ],

    tiempo: ['M22 12a10 10 0 1 1-20 0a10 10 0 0 1 20 0Z', 'M12 6v6l4 2'],

    combustible: [
        'M3 22h12',
        'M4 9h10',
        'M14 22V4a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v18',
        'M14 13h2a2 2 0 0 1 2 2v2a2 2 0 0 0 2 2a2 2 0 0 0 2-2V9.83a2 2 0 0 0-.59-1.42L18 5',
    ],

    co2: [
        'M11 20A7 7 0 0 1 9.8 6.1C15.5 5 17 4.48 19 2c1 2 2 4.18 2 8 0 5.5-4.78 10-10 10Z',
        'M2 21c0-3 1.85-5.36 5.08-6C9.5 14.52 12 13 13 12',
    ],
};

const resumenOperativo = computed(() => {
    return [
        {
            label: 'Distancia recorrida',
            value: formatoIndicador(props.resumenOperativo.distancia_km),
            unit: 'km',
            icono: iconos.distancia,
        },

        {
            label: 'Tiempo registrado',
            value: formatoIndicador(props.resumenOperativo.tiempo_horas),
            unit: 'h',
            icono: iconos.tiempo,
        },

        {
            label: 'Combustible estimado',
            value: formatoIndicador(props.resumenOperativo.combustible_litros),
            unit: 'L',
            icono: iconos.combustible,
        },

        {
            label: 'CO₂ estimado',
            value: formatoIndicador(props.resumenOperativo.co2_kg),
            unit: 'kg',
            icono: iconos.co2,
        },
    ];
});

/* ───────── Últimos envíos: tabla con pestañas ───────── */
const vista = ref('recientes');

const totalAtencion = computed(
    () =>
        props.alertas.vencidos + props.alertas.en_riesgo + props.alertas.fallos,
);

const vistas = computed(() => [
    { key: 'recientes', label: 'Últimos envíos' },

    {
        key: 'atencion',
        label: totalAtencion.value
            ? `Requieren atención (${totalAtencion.value})`
            : 'Requieren atención',
    },
]);

// Los fallos no entran al filtro "Solo con alerta" del listado: el enlace cubre vencidos y por vencer.
const hayAlertasAbiertas = computed(
    () => props.alertas.vencidos + props.alertas.en_riesgo > 0,
);

const enlaceVista = computed(() => {
    if (vista.value === 'atencion' && hayAlertasAbiertas.value) {
        return { href: '/envios?alerta=1', texto: 'Ver envíos con alerta' };
    }

    return { href: '/envios', texto: 'Ver todos' };
});

const fmtTiempo = (h: number) => {
    if (h < 1) return `${Math.max(1, Math.round(h * 60))} min`;
    if (h < 48) return `${Math.round(h)} h`;
    return `${Math.floor(h / 24)} días`;
};

const describir = (i: Incidencia) => {
    if (i.tipo === 'FALLO') {
        return {
            label: estadoInfo[i.estado].label,
            color: estadoInfo[i.estado].color,
            detalle: `Compromiso: ${fmtFecha(i.fecha_comprometida)}`,
        };
    }

    if (i.tipo === 'VENCIDO') {
        return {
            label: slaInfo.vencido.label,
            color: slaInfo.vencido.color,
            detalle: `Vencido hace ${fmtTiempo(i.horas)}`,
        };
    }

    return {
        label: slaInfo.en_riesgo.label,
        color: slaInfo.en_riesgo.color,
        detalle: `Vence en ${fmtTiempo(i.horas)}`,
    };
};

const incidenciasVista = computed(() =>
    props.incidencias.map((i) => ({ ...i, ...describir(i) })),
);

// Entrega estimada: "Hoy, 3:20 p. m.", "Mañana, ..." o fecha corta.
const fmtEntrega = (iso: string) => {
    const d = new Date(iso);

    const hora = d.toLocaleTimeString('es-SV', {
        hour: 'numeric',
        minute: '2-digit',
    });

    const hoyFecha = new Date();
    const manana = new Date(hoyFecha.getTime() + 86_400_000);

    if (d.toDateString() === hoyFecha.toDateString()) return `Hoy, ${hora}`;
    if (d.toDateString() === manana.toDateString()) return `Mañana, ${hora}`;
    return `${d.toLocaleDateString('es-SV', { day: 'numeric', month: 'short' })}, ${hora}`;
};

// Completado, por reprogramar o fecha prevista (en rojo si ya venció).
const entrega = (r: { estado: Estado; fecha_comprometida: string }) => {
    if (r.estado === 'ENTREGADO')
        return {
            texto: 'Completado',
            icono: CheckCircle2,
            color: colors.verde,
        };

    if (r.estado === 'FALLIDO' || r.estado === 'DEVUELTO')
        return {
            texto: 'Reprogramar',
            icono: AlertTriangle,
            color: colors.naranjaClaro,
        };

    const vencido = new Date(r.fecha_comprometida).getTime() < Date.now();

    return {
        texto: fmtEntrega(r.fecha_comprometida),
        icono: Clock,
        color: vencido ? colors.rojo : undefined,
    };
};

const recientesVista = computed(() =>
    props.recientes.map((r) => ({ ...r, entrega: entrega(r) })),
);

const columnas = computed<Columna[]>(() => [
    { label: 'ID' },
    { label: 'Cliente' },
    { label: 'Destino' },
    { label: vista.value === 'atencion' ? 'Situación' : 'Estado' },
    { label: vista.value === 'atencion' ? 'Detalle' : 'Entrega estimada' },
    { label: 'Acciones' },
]);

const sinFilas = computed(() =>
    vista.value === 'atencion'
        ? !incidenciasVista.value.length
        : !props.recientes.length,
);

const abrir = (id: number) => router.visit(`/envios/${id}`);

/* ───────── Análisis en pestañas ───────── */
const tab = ref('mensual');

const tabs = [
    { key: 'mensual', label: 'Envíos por mes' },
    { key: 'sucursal', label: 'Cumplimiento por sucursal' },
] as const;

const maxMes = Math.max(1, ...props.mensual.map((m) => m.total));

const totalMeses = computed(() =>
    props.mensual.reduce((a, m) => a + m.total, 0),
);

const alturaBarra = (total: number) =>
    `${Math.max((total / maxMes) * 80, total ? 4 : 1)}%`;

const filasSucursal = computed<BarRow[]>(() =>
    props.sucursales.map((s) => {
        const pct = porcentaje(s.a_tiempo, s.cerrados);

        return {
            key: s.sucursal,
            label: s.sucursal,
            width: pct ?? 0,
            color: soft(colors.azulClaro, '99'),
            detail:
                pct === null
                    ? 'Sin entregas cerradas'
                    : `${pct.toFixed(1)}% a tiempo`,
            accent: `${s.total} envíos`,
        };
    }),
);

const resumenAnalisis = computed(() =>
    tab.value === 'mensual'
        ? `${totalMeses.value.toLocaleString('es-SV')} envíos en los últimos 6 meses`
        : 'Últimos 30 días, por sucursal de origen',
);
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
        <!-- Encabezado: título a la izquierda y botones Operación / Analítica BI a la derecha. -->
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h1
                    class="text-2xl font-semibold tracking-tight text-foreground"
                >
                    Bienvenido<span v-if="nombre">, {{ nombre }}</span>
                </h1>

                <p class="mt-1 text-sm text-muted-foreground">
                    {{ hoyTexto }} · Resumen del estado operativo de la
                    distribución
                </p>
            </div>

            <div class="flex gap-2" role="tablist">
                <button
                    v-for="item in modos"
                    :key="item.key"
                    type="button"
                    role="tab"
                    :aria-selected="modo === item.key"
                    class="inline-flex h-10 items-center gap-2 rounded-lg border px-4 text-sm font-medium transition-colors"
                    :class="
                        modo === item.key
                            ? 'text-white'
                            : 'border-sidebar-border/70 bg-card text-muted-foreground hover:text-foreground'
                    "
                    :style="
                        modo === item.key
                            ? {
                                  backgroundColor: colors.azulClaro,
                                  borderColor: colors.azulClaro,
                              }
                            : undefined
                    "
                    @click="modo = item.key"
                >
                    <component
                        :is="item.icon"
                        class="h-4 w-4"
                        aria-hidden="true"
                    />

                    {{ item.label }}
                </button>
            </div>
        </header>

        <template v-if="modo === 'operacion'">
            <!-- Indicadores clave. -->
            <StatCards :items="resumen" :cols="4" variant="dashboard" />

            <!-- Estado de entregas y eficiencia de rutas -->
            <div class="grid grid-cols-1 gap-4 xl:grid-cols-2">
                <!-- Estado de las entregas -->
                <section
                    class="min-w-0 rounded-xl border border-sidebar-border/70 bg-card p-4 shadow-sm sm:p-5"
                    aria-label="Estado de las entregas"
                >
                    <BiPanelHead
                        title="Estado de las entregas"
                        description="Distribución de los envíos según su estado actual."
                    />
                    <template v-if="totalEnvios">
                        <div
                            class="mt-4 rounded-2xl border border-sidebar-border/70 bg-gradient-to-br from-card to-muted/10 p-3 sm:p-4"
                        >
                            <div
                                class="flex flex-col items-center gap-5 sm:flex-row sm:items-center sm:justify-center sm:gap-8"
                            >
                                <div
                                    class="relative h-40 w-40 shrink-0 sm:h-44 sm:w-44"
                                >
                                    <div
                                        class="absolute inset-2 rounded-full border border-dashed border-sidebar-border/70"
                                    ></div>
                                    <svg
                                        viewBox="0 0 120 120"
                                        class="h-full w-full -rotate-90 drop-shadow-sm"
                                        role="img"
                                        :aria-label="`${totalEnvios} envíos distribuidos por estado`"
                                    >
                                        <circle
                                            cx="60"
                                            cy="60"
                                            :r="RADIO"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="16"
                                            class="text-muted/40"
                                        />
                                        <circle
                                            v-for="s in segmentos"
                                            :key="s.label"
                                            cx="60"
                                            cy="60"
                                            :r="RADIO"
                                            fill="none"
                                            :stroke="s.color"
                                            stroke-width="16"
                                            stroke-linecap="round"
                                            :stroke-dasharray="`${s.largo} ${CIRCUNFERENCIA - s.largo}`"
                                            :stroke-dashoffset="s.desfase"
                                        />
                                    </svg>
                                    <div
                                        class="absolute inset-0 flex flex-col items-center justify-center"
                                    >
                                        <span
                                            class="text-2xl font-bold tracking-tight text-foreground sm:text-[1.8rem]"
                                            >{{
                                                totalEnvios.toLocaleString(
                                                    'es-SV',
                                                )
                                            }}</span
                                        >
                                        <span
                                            class="text-[11px] tracking-[0.18em] text-muted-foreground uppercase"
                                            >Total</span
                                        >
                                    </div>
                                </div>
                                <ul class="w-full max-w-sm min-w-0 space-y-2.5">
                                    <li
                                        v-for="g in grupos"
                                        :key="g.label"
                                        class="flex items-center gap-2 rounded-lg border border-transparent bg-muted/20 px-2 py-1.5 text-sm transition-colors hover:border-sidebar-border/70"
                                    >
                                        <span
                                            class="h-2.5 w-2.5 shrink-0 rounded-full ring-4 ring-white/60"
                                            :style="{
                                                backgroundColor: g.color,
                                            }"
                                        />
                                        <span
                                            class="min-w-0 flex-1 text-foreground"
                                            >{{ g.label }}</span
                                        >
                                        <span
                                            class="font-semibold text-foreground tabular-nums"
                                            >{{ g.total }}</span
                                        >
                                        <span
                                            class="w-12 text-right text-muted-foreground tabular-nums"
                                            >{{ pctGrupo(g.total) }}%</span
                                        >
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </template>
                    <p
                        v-else
                        class="py-8 text-center text-sm text-muted-foreground"
                    >
                        Aún no hay envíos registrados.
                    </p>
                </section>

                <!-- Eficiencia de rutas -->
                <section
                    class="min-w-0 rounded-xl border border-sidebar-border/70 bg-card p-4 shadow-sm sm:p-5"
                    aria-label="Eficiencia de rutas"
                >
                    <BiPanelHead
                        title="Eficiencia de rutas"
                        description="Puntualidad de paradas registrada en los últimos 14 días."
                    />
                    <div v-if="eficiencia.length" class="mt-5">
                        <div
                            class="mb-2 flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground"
                        >
                            <span>Eficiencia por despacho</span>
                            <span>Objetivo: 90%</span>
                        </div>
                        <div class="relative">
                            <div
                                class="pointer-events-none absolute inset-0 flex flex-col justify-between pb-7"
                            >
                                <div
                                    class="border-t border-dashed border-sidebar-border/70"
                                />
                                <div
                                    class="border-t border-dashed border-sidebar-border/70"
                                />
                                <div
                                    class="border-t border-sidebar-border/70"
                                />
                            </div>
                            <div
                                class="relative grid h-36 grid-cols-5 gap-3"
                                role="img"
                                aria-label="Porcentaje de paradas atendidas a tiempo por despacho"
                            >
                                <div
                                    v-for="e in eficiencia"
                                    :key="e.codigo"
                                    class="flex min-w-0 flex-col items-center justify-end"
                                    :title="`${e.codigo}: ${e.pct.toFixed(1)}% a tiempo`"
                                >
                                    <span
                                        class="mb-1 text-xs font-semibold text-foreground tabular-nums"
                                        >{{ e.pct.toFixed(0) }}%</span
                                    >
                                    <div
                                        class="flex h-24 w-full items-end justify-center"
                                    >
                                        <div
                                            class="w-full max-w-10 rounded-t-md transition-all duration-300"
                                            :style="{
                                                height: `${Math.max(e.pct, 2)}%`,
                                                backgroundColor:
                                                    colorEficiencia(e.pct),
                                            }"
                                        />
                                    </div>
                                    <span
                                        class="mt-2 max-w-full truncate text-xs text-muted-foreground"
                                        >{{ e.codigo }}</span
                                    >
                                </div>
                            </div>
                        </div>
                        <div
                            class="mt-4 flex flex-wrap gap-x-4 gap-y-2 border-t border-sidebar-border/70 pt-3 text-xs text-muted-foreground"
                        >
                            <span
                                ><span
                                    class="mr-1 inline-block h-2 w-2 rounded-full bg-blue-500"
                                />≥ 90% Óptima</span
                            >
                            <span
                                ><span
                                    class="mr-1 inline-block h-2 w-2 rounded-full bg-amber-500"
                                />75–89% Mejorable</span
                            >
                            <span
                                ><span
                                    class="mr-1 inline-block h-2 w-2 rounded-full bg-red-500"
                                />&lt; 75% Baja</span
                            >
                        </div>
                    </div>
                    <p
                        v-else
                        class="rounded-lg border border-dashed border-sidebar-border/70 px-4 py-8 text-center text-sm text-muted-foreground"
                    >
                        No hay paradas evaluadas todavía.
                    </p>
                </section>

                <!-- Resumen operativo: una fila de indicadores -->
                <section
                    class="min-w-0 rounded-xl border border-sidebar-border/70 bg-card p-4 shadow-sm sm:p-5 xl:col-span-2"
                    aria-label="Resumen operativo"
                >
                    <BiPanelHead
                        title="Resumen operativo"
                        description="Indicadores calculados con recorridos y registros de los últimos 14 días."
                    />
                    <dl
                        class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4"
                    >
                        <div
                            v-for="r in resumenOperativo"
                            :key="r.label"
                            class="group flex min-w-0 items-center gap-3 rounded-2xl border border-sidebar-border/70 bg-gradient-to-br from-card to-muted/10 p-3.5 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-sidebar-border hover:shadow-md"
                        >
                            <span
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-transparent shadow-sm"
                                :style="{
                                    backgroundColor: soft(colors.azulClaro),
                                    color: colors.azulClaro,
                                }"
                            >
                                <svg
                                    viewBox="0 0 24 24"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    class="h-5 w-5"
                                    aria-hidden="true"
                                >
                                    <path
                                        v-for="d in r.icono"
                                        :key="d"
                                        :d="d"
                                    />
                                </svg>
                            </span>
                            <div class="min-w-0 flex-1">
                                <dt
                                    class="text-[11px] font-medium tracking-[0.14em] text-muted-foreground uppercase"
                                >
                                    {{ r.label }}
                                </dt>
                                <dd
                                    class="mt-1 flex items-end gap-1 text-xl font-bold tracking-tight text-foreground"
                                >
                                    <span>{{ r.value }}</span>
                                    <span
                                        class="pb-0.5 text-sm font-medium text-muted-foreground"
                                        >{{ r.unit }}</span
                                    >
                                </dd>
                            </div>
                        </div>
                    </dl>
                    <aside
                        class="mt-5 rounded-2xl border border-sidebar-border/70 bg-muted/20 p-4 sm:p-5"
                        aria-label="Cómo se calculan los indicadores"
                    >
                        <div
                            class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between"
                        >
                            <div>
                                <p
                                    class="text-[10px] font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                                >
                                    Sobre los datos
                                </p>
                                <h3
                                    class="mt-1 text-sm font-semibold text-foreground"
                                >
                                    Cómo se calculan estos indicadores
                                </h3>
                            </div>
                            <p
                                class="text-xs leading-relaxed text-muted-foreground"
                            >
                                Criterios para interpretar el resumen operativo.
                            </p>
                        </div>

                        <div class="mt-4 grid gap-3 md:grid-cols-3">
                            <article
                                class="rounded-xl border border-sidebar-border/60 bg-card p-3.5 shadow-sm"
                            >
                                <div class="flex items-center gap-2.5">
                                    <span
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-emerald-500/10 text-xs font-bold text-emerald-700 dark:text-emerald-300"
                                        aria-hidden="true"
                                    >
                                        km
                                    </span>
                                    <h4
                                        class="text-sm font-semibold text-foreground"
                                    >
                                        Combustible
                                    </h4>
                                </div>
                                <p
                                    class="mt-3 text-xs leading-relaxed text-muted-foreground"
                                >
                                    Se estima con los kilómetros recorridos y el
                                    rendimiento (km/galón) registrado para cada
                                    vehículo.
                                </p>
                            </article>

                            <article
                                class="rounded-xl border border-sidebar-border/60 bg-card p-3.5 shadow-sm"
                            >
                                <div class="flex items-center gap-2.5">
                                    <span
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-sky-500/10 text-[11px] font-bold text-sky-700 dark:text-sky-300"
                                        aria-hidden="true"
                                    >
                                        CO₂
                                    </span>
                                    <h4
                                        class="text-sm font-semibold text-foreground"
                                    >
                                        Emisiones de CO₂
                                    </h4>
                                </div>
                                <p
                                    class="mt-3 text-xs leading-relaxed text-muted-foreground"
                                >
                                    Se calculan con un factor de 2.31 kg de CO₂
                                    por litro de combustible.
                                </p>
                            </article>

                            <article
                                class="rounded-xl border border-sidebar-border/60 bg-card p-3.5 shadow-sm"
                            >
                                <div class="flex items-center gap-2.5">
                                    <span
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-amber-500/10 text-lg font-semibold text-amber-700 dark:text-amber-300"
                                        aria-hidden="true"
                                    >
                                        —
                                    </span>
                                    <h4
                                        class="text-sm font-semibold text-foreground"
                                    >
                                        Sin datos suficientes
                                    </h4>
                                </div>
                                <p
                                    class="mt-3 text-xs leading-relaxed text-muted-foreground"
                                >
                                    Se muestra «—» cuando no hay registros
                                    suficientes para calcular un indicador.
                                </p>
                            </article>
                        </div>
                    </aside>
                </section>
            </div>
            <!-- Últimos envíos. -->
            <section
                class="overflow-hidden rounded-2xl border border-sidebar-border/70 bg-card shadow-sm"
                aria-label="Últimos envíos"
            >
                <div class="bg-muted/10 px-4 pt-4 sm:px-5 sm:pt-5">
                    <BiTabs
                        v-model="vista"
                        :tabs="vistas"
                        label="Vistas de envíos"
                    >
                        <Link
                            :href="enlaceVista.href"
                            class="text-sm font-medium text-blue-700 hover:underline dark:text-blue-300"
                            >{{ enlaceVista.texto }} →</Link
                        >
                    </BiTabs>
                </div>

                <BiTable
                    :columns="columnas"
                    :vacio="sinFilas"
                    variant="dashboard"
                    :empty-title="
                        vista === 'atencion'
                            ? 'Sin incidencias activas'
                            : 'No hay envíos recientes'
                    "
                    :empty-hint="
                        vista === 'atencion'
                            ? 'El seguimiento está al día.'
                            : 'Los nuevos envíos aparecerán aquí.'
                    "
                >
                    <!-- Envíos que requieren atención. -->
                    <template v-if="vista === 'atencion'">
                        <BiTableRow
                            v-for="i in incidenciasVista"
                            :key="i.id_envio"
                            variant="dashboard"
                            @select="abrir(i.id_envio)"
                        >
                            <td
                                class="px-5 py-4"
                                :style="{
                                    boxShadow: `inset 3px 0 0 ${i.color}`,
                                }"
                            >
                                <span
                                    class="inline-flex rounded-md bg-muted/50 px-2 py-1 font-mono text-xs font-semibold tracking-wide text-foreground"
                                    >{{ i.codigo_guia }}</span
                                >
                            </td>

                            <td class="px-5 py-4 text-foreground">
                                {{ i.cliente }}
                            </td>

                            <td class="px-5 py-4 text-muted-foreground">
                                {{ i.destino }}
                            </td>

                            <td class="px-5 py-4">
                                <BiBadge :color="i.color" :label="i.label" />
                            </td>

                            <td
                                class="px-5 py-4 whitespace-nowrap text-muted-foreground"
                            >
                                {{ i.detalle }}
                            </td>

                            <td class="px-5 py-4">
                                <button
                                    type="button"
                                    class="rounded-lg border border-sidebar-border/70 bg-card p-2 text-muted-foreground transition-colors hover:border-sidebar-border hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    aria-label="Ver detalle del envío"
                                    @click.stop="abrir(i.id_envio)"
                                >
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        class="h-4 w-4"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"
                                        />

                                        <path
                                            d="M15 12a3 3 0 1 1-6 0a3 3 0 0 1 6 0Z"
                                        />
                                    </svg>
                                </button>
                            </td>
                        </BiTableRow>
                    </template>
                    <!-- Últimos envíos registrados. -->
                    <template v-else>
                        <BiTableRow
                            v-for="r in recientesVista"
                            :key="r.id_envio"
                            variant="dashboard"
                            @select="abrir(r.id_envio)"
                        >
                            <td class="px-5 py-4">
                                <span
                                    class="inline-flex rounded-md bg-muted/50 px-2 py-1 font-mono text-xs font-semibold tracking-wide text-foreground"
                                    >{{ r.codigo_guia }}</span
                                >
                            </td>

                            <td class="px-5 py-4 text-foreground">
                                {{ r.cliente }}
                            </td>

                            <td class="px-5 py-4 text-muted-foreground">
                                {{ r.destino }}
                            </td>

                            <td class="px-5 py-4">
                                <BiBadge
                                    :color="estadoInfo[r.estado].color"
                                    :label="estadoInfo[r.estado].label"
                                />
                            </td>

                            <td class="px-5 py-4 whitespace-nowrap">
                                <span
                                    class="inline-flex items-center gap-2 text-sm"
                                    :class="
                                        r.entrega.color
                                            ? ''
                                            : 'text-muted-foreground'
                                    "
                                    :style="{ color: r.entrega.color }"
                                >
                                    <component
                                        :is="r.entrega.icono"
                                        class="h-4 w-4"
                                        aria-hidden="true"
                                    />

                                    {{ r.entrega.texto }}
                                </span>
                            </td>

                            <td class="px-5 py-4">
                                <button
                                    type="button"
                                    class="rounded-lg border border-sidebar-border/70 bg-card p-2 text-muted-foreground transition-colors hover:border-sidebar-border hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    aria-label="Ver detalle del envío"
                                    @click.stop="abrir(r.id_envio)"
                                >
                                    <svg
                                        viewBox="0 0 24 24"
                                        fill="none"
                                        stroke="currentColor"
                                        stroke-width="2"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        class="h-4 w-4"
                                        aria-hidden="true"
                                    >
                                        <path
                                            d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"
                                        />

                                        <path
                                            d="M15 12a3 3 0 1 1-6 0a3 3 0 0 1 6 0Z"
                                        />
                                    </svg>
                                </button>
                            </td>
                        </BiTableRow>
                    </template>
                </BiTable>
            </section>
            <!-- Análisis en pestañas: mismo patrón que la sección de análisis de Envíos. -->
            <section
                class="rounded-xl border border-sidebar-border/70 bg-card p-4 shadow-sm sm:p-5"
                aria-label="Análisis"
            >
                <BiTabs v-model="tab" :tabs="tabs">
                    <p class="text-sm leading-relaxed text-muted-foreground">
                        {{ resumenAnalisis }}
                    </p>
                </BiTabs>

                <div v-if="tab === 'mensual'" class="pt-5">
                    <div class="mb-4 flex items-center justify-between gap-3">
                        <BiPanelHead
                            title="Envíos registrados por mes"
                            description="Tendencia de los últimos 6 meses."
                        />
                        <span
                            class="inline-flex shrink-0 items-center gap-2 text-xs text-muted-foreground"
                        >
                            <span
                                class="h-2.5 w-2.5 rounded-sm"
                                :style="{
                                    backgroundColor: soft(
                                        colors.azulClaro,
                                        'CC',
                                    ),
                                }"
                            />
                            Mes actual
                        </span>
                    </div>

                    <div
                        class="rounded-2xl border border-sidebar-border/70 bg-muted/10 p-4 sm:p-5"
                    >
                        <div
                            class="relative flex h-52 items-end justify-between gap-2 px-2 pt-4 pb-2 sm:gap-3"
                            role="img"
                            aria-label="Envíos registrados por mes, últimos 6 meses"
                        >
                            <div
                                class="pointer-events-none absolute inset-x-2 top-1/4 border-t border-dashed border-sidebar-border/50"
                            />
                            <div
                                class="pointer-events-none absolute inset-x-2 top-1/2 border-t border-dashed border-sidebar-border/50"
                            />
                            <div
                                class="pointer-events-none absolute inset-x-2 top-3/4 border-t border-dashed border-sidebar-border/50"
                            />

                            <div
                                v-for="(m, i) in mensual"
                                :key="m.label + i"
                                class="relative flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-2"
                                :title="`${m.label}: ${m.total} envíos`"
                            >
                                <span
                                    class="text-[11px] font-medium text-muted-foreground tabular-nums"
                                    >{{ m.total || '' }}</span
                                >

                                <div
                                    class="relative w-full max-w-12 rounded-t-xl transition-[height,background-color] duration-300"
                                    :style="{
                                        height: alturaBarra(m.total),
                                        backgroundColor:
                                            i === mensual.length - 1
                                                ? soft(colors.azulClaro, 'CC')
                                                : soft(colors.azulClaro, '38'),
                                    }"
                                />

                                <span
                                    class="text-[11px] font-medium text-muted-foreground"
                                    >{{ m.label }}</span
                                >
                            </div>
                        </div>
                    </div>
                </div>

                <div v-else class="pt-5">
                    <BiPanelHead
                        title="Cumplimiento de entrega por sucursal"
                        description="Porcentaje de envíos cerrados que se entregaron dentro del compromiso."
                    />

                    <BiBarList
                        :rows="filasSucursal"
                        :active="null"
                        empty="No hay datos disponibles en los últimos 30 días."
                    />
                </div>
            </section>
        </template>

        <!-- Vista de Analítica BI. -->
        <section
            v-else
            class="rounded-xl border border-sidebar-border/70 bg-card p-4 shadow-sm sm:p-5"
            aria-label="Analítica BI"
        >
            <BiPanelHead
                title="Analítica BI"
                description="Datos → Procesamiento → Indicadores → Análisis"
            />

            <ol class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <li
                    v-for="(step, index) in flowSteps"
                    :key="step"
                    class="flex items-center gap-3 rounded-lg border border-sidebar-border/70 p-3 text-sm text-foreground"
                >
                    <span
                        class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-muted text-xs font-semibold text-muted-foreground"
                        >{{ index + 1 }}</span
                    >
                    <span>{{ step }}</span>
                </li>
            </ol>
            <!-- Analítica BI (Streamlit incrustado): coloca aquí tu iframe. -->
        </section>
    </div>
</template>
