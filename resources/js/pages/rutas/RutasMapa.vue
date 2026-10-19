<script setup lang="ts">
import {
    computed,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch,
} from 'vue';
import { Head } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import { colors } from '@/UI/Colors';
import {
    estadoInfo,
    estadosOrden,
    type EstadoEnvio,
} from '@/config/Envios/envios';
import MapaRutas from './MapaRutas.vue';
import {
    visualRuta,
    type DatosMapa,
    type EnvioMapa,
} from '@/config/Rutas/Mapa';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Rutas', href: '/rutas' },
            { title: 'Inteligencia geográfica', href: '#' },
        ],
    },
});

const props = defineProps<{
    territorios: DatosMapa['catalogos']['territorios'];
}>();

const filtros = reactive({
    dias: '7',
    estado: '',
    ruta: '',
    territorio: '',
    zona: '',
});
const datos = ref<DatosMapa | null>(null);
const error = ref<string | null>(null);
const cargando = ref(false);
const filtrosTocados = ref(false);
const filtrosAbiertos = ref(false);
const panelActivo = ref<'resumen' | 'territorios' | 'recorridos' | 'estados'>(
    'resumen',
);
const panelesAnalisis = [
    { id: 'resumen', etiqueta: 'Resumen' },
    { id: 'territorios', etiqueta: 'Territorios' },
    { id: 'recorridos', etiqueta: 'Recorridos' },
    { id: 'estados', etiqueta: 'Estados' },
] as const;
let solicitud: AbortController | null = null;

const cargarDatos = async (): Promise<void> => {
    solicitud?.abort();
    const actual = new AbortController();
    solicitud = actual;
    cargando.value = true;
    error.value = null;

    const parametros = new URLSearchParams();
    for (const [clave, valor] of Object.entries(filtros)) {
        if (valor !== '') {
            parametros.set(clave, valor);
        }
    }

    try {
        const respuesta = await fetch(
            `/rutas/mapa/datos?${parametros.toString()}`,
            {
                signal: actual.signal,
                headers: { Accept: 'application/json' },
            },
        );

        if (!respuesta.ok) {
            throw new Error(
                `No se pudieron cargar los datos del mapa (${respuesta.status}).`,
            );
        }

        datos.value = (await respuesta.json()) as DatosMapa;
    } catch (err) {
        if (actual.signal.aborted) {
            return;
        }
        error.value =
            err instanceof Error
                ? err.message
                : 'No se pudieron cargar los datos del mapa.';
    } finally {
        if (!actual.signal.aborted) {
            cargando.value = false;
        }
    }
};

onMounted(() => {
    void cargarDatos();
});

onBeforeUnmount(() => {
    solicitud?.abort();
});

watch(filtros, () => {
    filtrosTocados.value = true;
    void cargarDatos();
});

const territoriosDisponibles = computed(
    () => datos.value?.catalogos.territorios ?? props.territorios,
);
const zonasDisponibles = computed(() => datos.value?.catalogos.zonas ?? []);
const kpis = computed(() => datos.value?.kpis);
const maxEnviosTerritorio = computed(() =>
    Math.max(
        1,
        ...(datos.value?.territorios.map((territorio) => territorio.total) ??
            []),
    ),
);
const enviosConUbicacion = computed(
    () =>
        datos.value?.envios.filter(
            (envio) => envio.lat !== null && envio.lng !== null,
        ).length ?? 0,
);
const opcionesPeriodo = [
    { value: '1', label: 'Hoy' },
    { value: '7', label: 'Últimos 7 días' },
    { value: '14', label: 'Últimos 14 días' },
    { value: '30', label: 'Últimos 30 días' },
    { value: '90', label: 'Últimos 90 días' },
    { value: '0', label: 'Todo el historial' },
];

const estadisticas = computed(() => {
    const resumen = kpis.value;
    if (!resumen) {
        return [];
    }

    return [
        { label: 'Envíos', valor: resumen.total, color: colors.azulOscuro },
        {
            label: 'Pendientes',
            valor: resumen.pendientes,
            color: colors.grisMedio,
        },
        { label: 'En ruta', valor: resumen.en_ruta, color: colors.azulClaro },
        { label: 'Entregados', valor: resumen.entregados, color: colors.verde },
        {
            label: 'Tasa de entrega',
            valor: `${resumen.tasa_entrega.toFixed(1)}%`,
            color: colors.cian,
        },
        {
            label: 'Incidencias',
            valor: resumen.incidencias,
            color: colors.rojo,
        },
    ];
});

const etiquetaEstado = (estado: string): string =>
    estado in estadoInfo ? estadoInfo[estado as EstadoEnvio].label : estado;

const colorEstado = (estado: string): string =>
    estadoInfo[estado as EstadoEnvio]?.color ?? colors.grisMedio;

const seleccionarRuta = (rutaId: number): void => {
    filtros.ruta = filtros.ruta === String(rutaId) ? '' : String(rutaId);
};

const limpiarFiltros = (): void => {
    filtros.dias = '7';
    filtros.estado = '';
    filtros.ruta = '';
    filtros.territorio = '';
    filtros.zona = '';
};

const tituloPeriodo = computed(() => {
    const opcion = opcionesPeriodo.find(
        (periodo) => periodo.value === filtros.dias,
    );
    return opcion?.label ?? 'Período seleccionado';
});

const territorioSeleccionado = (envio: EnvioMapa): string =>
    envio.territorio_padre
        ? `${envio.territorio} · ${envio.territorio_padre}`
        : envio.territorio;

type ClaveFiltro = 'dias' | 'estado' | 'ruta' | 'territorio' | 'zona';

const filtrosActivos = computed(() => {
    const activos: { clave: ClaveFiltro; texto: string }[] = [];

    if (filtros.dias !== '7') {
        activos.push({ clave: 'dias', texto: tituloPeriodo.value });
    }
    if (filtros.estado) {
        activos.push({
            clave: 'estado',
            texto: etiquetaEstado(filtros.estado),
        });
    }
    if (filtros.ruta) {
        const ruta = datos.value?.catalogos.rutas.find(
            (opcion) => String(opcion.id) === filtros.ruta,
        );
        activos.push({
            clave: 'ruta',
            texto: ruta?.codigo ?? `Ruta ${filtros.ruta}`,
        });
    }
    if (filtros.territorio) {
        const territorio = territoriosDisponibles.value.find(
            (opcion) => String(opcion.id) === filtros.territorio,
        );
        activos.push({
            clave: 'territorio',
            texto: territorio?.nombre ?? 'Territorio seleccionado',
        });
    }
    if (filtros.zona) {
        const zona = zonasDisponibles.value.find(
            (opcion) => String(opcion.id) === filtros.zona,
        );
        activos.push({
            clave: 'zona',
            texto: zona?.nombre ?? 'Zona seleccionada',
        });
    }

    return activos;
});

const quitarFiltro = (clave: ClaveFiltro): void => {
    filtros[clave] = clave === 'dias' ? '7' : '';
};
</script>

<template>
    <Head title="Inteligencia geográfica de rutas" />

    <main
        class="flex h-full min-h-0 flex-1 flex-col gap-5 overflow-y-auto p-4 md:p-6"
    >
        <header class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-sm font-medium text-primary">
                    Logística · BI geográfico
                </p>
                <h1
                    class="mt-1 text-2xl font-semibold tracking-tight text-foreground"
                >
                    Inteligencia geográfica de rutas
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Desempeño, concentración de envíos y análisis operativo por
                    territorio.
                </p>
            </div>
        </header>

        <section
            class="rounded-xl border border-sidebar-border/70 bg-card shadow-sm"
            aria-label="Filtros del análisis geográfico"
        >
            <div class="flex flex-wrap items-center justify-between gap-3 p-3">
                <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                    <span
                        class="shrink-0 text-sm font-semibold text-foreground"
                    >
                        {{
                            filtrosActivos.length
                                ? 'Filtros aplicados'
                                : 'Vista general'
                        }}
                    </span>
                    <span class="shrink-0 text-xs text-muted-foreground">
                        {{ tituloPeriodo }}
                    </span>
                    <template v-if="filtrosActivos.length">
                        <button
                            v-for="filtro in filtrosActivos"
                            :key="filtro.clave"
                            type="button"
                            class="inline-flex min-h-7 items-center gap-1 rounded-full border border-sidebar-border/70 bg-muted/50 px-2.5 text-xs text-foreground transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            :aria-label="`Quitar filtro ${filtro.texto}`"
                            @click="quitarFiltro(filtro.clave)"
                        >
                            {{ filtro.texto }}
                            <span
                                aria-hidden="true"
                                class="text-muted-foreground"
                                >×</span
                            >
                        </button>
                    </template>
                    <span v-else class="text-xs text-muted-foreground">
                        Sin filtros adicionales
                    </span>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <button
                        v-if="filtrosActivos.length"
                        type="button"
                        class="min-h-10 rounded-lg px-3 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        @click="limpiarFiltros"
                    >
                        Limpiar
                    </button>
                    <button
                        type="button"
                        class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-sidebar-border/70 bg-background px-3 text-sm font-medium text-foreground transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        aria-controls="panel-filtros-mapa"
                        :aria-expanded="filtrosAbiertos"
                        @click="filtrosAbiertos = !filtrosAbiertos"
                    >
                        <span>Filtros</span>
                        <span
                            v-if="filtrosActivos.length"
                            class="flex h-5 min-w-5 items-center justify-center rounded-full bg-primary px-1 text-xs font-semibold text-primary-foreground"
                        >
                            {{ filtrosActivos.length }}
                        </span>
                        <svg
                            class="h-4 w-4 transition-transform"
                            :class="filtrosAbiertos ? 'rotate-180' : ''"
                            viewBox="0 0 20 20"
                            fill="none"
                            aria-hidden="true"
                        >
                            <path
                                d="m5 7.5 5 5 5-5"
                                stroke="currentColor"
                                stroke-width="1.7"
                                stroke-linecap="round"
                                stroke-linejoin="round"
                            />
                        </svg>
                    </button>
                </div>
            </div>

            <div
                v-if="filtrosAbiertos"
                id="panel-filtros-mapa"
                class="grid gap-3 border-t border-sidebar-border/70 p-4 sm:grid-cols-2 lg:grid-cols-5"
            >
                <label class="grid gap-1.5 text-sm">
                    <span class="font-medium text-foreground">Período</span>
                    <select
                        v-model="filtros.dias"
                        class="h-10 rounded-lg border border-sidebar-border/70 bg-background px-3"
                    >
                        <option
                            v-for="periodo in opcionesPeriodo"
                            :key="periodo.value"
                            :value="periodo.value"
                        >
                            {{ periodo.label }}
                        </option>
                    </select>
                </label>
                <label class="grid gap-1.5 text-sm">
                    <span class="font-medium text-foreground"
                        >Estado del envío</span
                    >
                    <select
                        v-model="filtros.estado"
                        class="h-10 rounded-lg border border-sidebar-border/70 bg-background px-3"
                    >
                        <option value="">Todos los estados</option>
                        <option
                            v-for="estado in estadosOrden"
                            :key="estado"
                            :value="estado"
                        >
                            {{ estadoInfo[estado].label }}
                        </option>
                    </select>
                </label>
                <label class="grid gap-1.5 text-sm">
                    <span class="font-medium text-foreground"
                        >Ruta / despacho</span
                    >
                    <select
                        v-model="filtros.ruta"
                        class="h-10 rounded-lg border border-sidebar-border/70 bg-background px-3"
                    >
                        <option value="">Todas las rutas</option>
                        <option
                            v-for="ruta in datos?.catalogos.rutas ?? []"
                            :key="ruta.id"
                            :value="String(ruta.id)"
                        >
                            {{ ruta.codigo }} · {{ ruta.fecha }} ·
                            {{ visualRuta(ruta.estado).etiqueta }}
                        </option>
                    </select>
                </label>
                <label class="grid gap-1.5 text-sm">
                    <span class="font-medium text-foreground">Territorio</span>
                    <select
                        v-model="filtros.territorio"
                        class="h-10 rounded-lg border border-sidebar-border/70 bg-background px-3"
                    >
                        <option value="">Todos los territorios</option>
                        <option
                            v-for="territorio in territoriosDisponibles"
                            :key="territorio.id"
                            :value="String(territorio.id)"
                        >
                            {{ territorio.nombre }} ·
                            {{ territorio.tipo.toLowerCase() }}
                        </option>
                    </select>
                </label>
                <label class="grid gap-1.5 text-sm">
                    <span class="font-medium text-foreground"
                        >Zona geográfica</span
                    >
                    <select
                        v-model="filtros.zona"
                        :disabled="!zonasDisponibles.length"
                        class="h-10 rounded-lg border border-sidebar-border/70 bg-background px-3 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <option value="">Todas las zonas</option>
                        <option
                            v-for="zona in zonasDisponibles"
                            :key="zona.id"
                            :value="String(zona.id)"
                        >
                            {{ zona.nombre }}
                        </option>
                    </select>
                </label>
            </div>
        </section>

        <p
            v-if="error"
            role="alert"
            class="rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800"
        >
            {{ error }}
        </p>

        <div
            class="grid gap-4 xl:h-[calc(100vh-17rem)] xl:min-h-[480px] xl:grid-cols-12 xl:items-stretch xl:overflow-hidden"
        >
            <section
                class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm xl:col-span-8 xl:flex xl:min-h-0 xl:flex-col"
            >
                <div
                    class="flex flex-wrap items-center justify-between gap-3 border-b border-sidebar-border/70 px-4 py-3"
                >
                    <div>
                        <h2 class="font-semibold text-foreground">
                            Distribución geográfica
                        </h2>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{ tituloPeriodo }} ·
                            {{ enviosConUbicacion }} envíos con ubicación
                            disponible
                        </p>
                    </div>
                </div>
                <div
                    v-if="datos"
                    class="h-[min(72vh,760px)] min-h-[480px] xl:h-full xl:min-h-0 xl:flex-1"
                >
                    <MapaRutas
                        :sucursales="datos.sucursales"
                        :despachos="datos.rutas"
                        :envios="datos.envios"
                        :zonas="datos.catalogos.zonas"
                        :seleccionado="
                            filtros.ruta ? Number(filtros.ruta) : null
                        "
                        :modo-zonas="false"
                        :mostrar-rutas="filtrosTocados"
                        @seleccionar="seleccionarRuta"
                    />
                </div>
                <div
                    v-else
                    class="flex h-[480px] items-center justify-center text-sm text-muted-foreground"
                >
                    {{
                        cargando
                            ? 'Cargando información geográfica…'
                            : 'No hay información para mostrar.'
                    }}
                </div>
                <p
                    v-if="datos && enviosConUbicacion < datos.kpis.total"
                    class="border-t border-sidebar-border/70 px-4 py-2 text-xs text-muted-foreground"
                >
                    {{ datos.kpis.total - enviosConUbicacion }} envío(s) no
                    tienen coordenadas reportadas en sus eventos. Solo se
                    muestran ubicaciones registradas en la base de datos; no se
                    estiman posiciones a partir del territorio.
                </p>
            </section>

            <aside
                class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm xl:col-span-4 xl:flex xl:h-full xl:max-h-full xl:min-h-0 xl:flex-col"
                aria-label="Análisis de rutas"
            >
                <div
                    class="grid shrink-0 grid-cols-4 border-b border-sidebar-border/70 p-1"
                    role="tablist"
                    aria-label="Secciones del análisis"
                >
                    <button
                        v-for="pestana in panelesAnalisis"
                        :key="pestana.id"
                        :id="`tab-${pestana.id}`"
                        type="button"
                        role="tab"
                        class="min-h-10 rounded-lg px-1 text-xs font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:text-sm"
                        :class="
                            panelActivo === pestana.id
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                        "
                        :aria-selected="panelActivo === pestana.id"
                        :aria-controls="`panel-${pestana.id}`"
                        @click="panelActivo = pestana.id"
                    >
                        {{ pestana.etiqueta }}
                    </button>
                </div>

                <div
                    id="panel-resumen"
                    role="tabpanel"
                    aria-labelledby="tab-resumen"
                    :hidden="panelActivo !== 'resumen'"
                    class="space-y-3 p-3 xl:min-h-0 xl:flex-1 xl:overflow-y-auto"
                >
                    <div class="flex items-center justify-between gap-2">
                        <div>
                            <h2 class="font-semibold text-foreground">
                                Indicadores
                            </h2>
                            <p class="text-xs text-muted-foreground">
                                {{ tituloPeriodo }}
                            </p>
                        </div>
                        <span
                            v-if="cargando"
                            class="text-xs text-muted-foreground"
                            role="status"
                            >Actualizando…</span
                        >
                    </div>
                    <div
                        v-if="datos"
                        class="grid grid-cols-2 gap-2"
                        aria-label="Indicadores de desempeño"
                    >
                        <article
                            v-for="stat in estadisticas"
                            :key="stat.label"
                            class="rounded-lg border border-sidebar-border/70 p-3"
                        >
                            <p
                                class="text-xs font-medium text-muted-foreground"
                            >
                                {{ stat.label }}
                            </p>
                            <p
                                class="mt-1 text-2xl font-semibold tracking-tight"
                                :style="{ color: stat.color }"
                            >
                                {{ stat.valor }}
                            </p>
                        </article>
                    </div>
                    <p
                        v-if="datos?.territorios.length"
                        class="rounded-lg border border-sidebar-border/70 p-3 text-sm text-muted-foreground"
                    >
                        Mayor volumen:
                        <strong class="text-foreground">
                            {{ datos.territorios[0].nombre }}
                        </strong>
                        · {{ datos.territorios[0].total }} envíos
                    </p>
                    <p
                        v-else
                        class="rounded-lg border border-sidebar-border/70 p-3 text-sm text-muted-foreground"
                    >
                        No hay envíos para los filtros seleccionados.
                    </p>
                </div>

                <div
                    id="panel-territorios"
                    role="tabpanel"
                    aria-labelledby="tab-territorios"
                    :hidden="panelActivo !== 'territorios'"
                    class="space-y-4 p-3 xl:min-h-0 xl:flex-1 xl:overflow-y-auto"
                >
                    <div>
                        <h2 class="font-semibold text-foreground">
                            Desempeño por territorio
                        </h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Volumen y entregas con los filtros del mapa.
                        </p>
                    </div>
                    <div v-if="datos?.territorios.length" class="space-y-4">
                        <div
                            v-for="territorio in datos.territorios"
                            :key="territorio.id"
                        >
                            <div
                                class="flex items-center justify-between gap-3 text-sm"
                            >
                                <div class="min-w-0">
                                    <p
                                        class="truncate font-medium text-foreground"
                                    >
                                        {{ territorio.nombre }}
                                    </p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ territorio.tipo.toLowerCase()
                                        }}<template v-if="territorio.padre">
                                            · {{ territorio.padre }}</template
                                        >
                                    </p>
                                </div>
                                <div class="shrink-0 text-right tabular-nums">
                                    <span
                                        class="font-semibold text-foreground"
                                        >{{ territorio.total }}</span
                                    >
                                    <span class="text-muted-foreground">
                                        envíos</span
                                    >
                                </div>
                            </div>
                            <div
                                class="mt-2 h-2 overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    class="h-full rounded-full"
                                    :style="{
                                        width: `${(territorio.total / maxEnviosTerritorio) * 100}%`,
                                        backgroundColor: colors.azulClaro,
                                    }"
                                />
                            </div>
                            <div
                                class="mt-1.5 flex flex-wrap justify-between gap-x-3 text-xs text-muted-foreground"
                            >
                                <span
                                    >{{ territorio.tasa_entrega.toFixed(1) }}%
                                    entregados</span
                                >
                                <span
                                    >{{ territorio.pendientes }} pendientes ·
                                    {{ territorio.en_ruta }} en ruta</span
                                >
                                <span
                                    >{{ territorio.envios_con_incidencias }} con
                                    incidencias</span
                                >
                            </div>
                        </div>
                    </div>
                    <p
                        v-else
                        class="py-8 text-center text-sm text-muted-foreground"
                    >
                        No hay envíos para los filtros seleccionados.
                    </p>
                    <p
                        v-if="datos && !zonasDisponibles.length"
                        class="border-t border-sidebar-border/70 pt-3 text-xs text-muted-foreground"
                    >
                        <template v-if="datos.zonas_soportadas">
                            No hay polígonos configurados todavía. El análisis
                            territorial sigue disponible por departamento,
                            municipio y distrito.
                        </template>
                        <template v-else>
                            Esta base no tiene la columna opcional de geometría
                            territorial; el mapa la omite y sigue funcionando
                            con la jerarquía territorial existente.
                        </template>
                    </p>
                </div>

                <div
                    id="panel-recorridos"
                    role="tabpanel"
                    aria-labelledby="tab-recorridos"
                    :hidden="panelActivo !== 'recorridos'"
                    class="space-y-3 p-3 xl:min-h-0 xl:flex-1 xl:overflow-y-auto"
                >
                    <div>
                        <h2 class="font-semibold text-foreground">
                            Recorridos e incidencias
                        </h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Selecciona un despacho para enfocarlo en el mapa.
                        </p>
                    </div>
                    <div v-if="datos?.rutas.length" class="space-y-3">
                        <button
                            v-for="ruta in datos.rutas"
                            :key="ruta.id"
                            type="button"
                            class="w-full rounded-lg border p-3 text-left transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            :class="
                                filtros.ruta === String(ruta.id)
                                    ? 'border-primary bg-primary/5'
                                    : 'border-sidebar-border/70 hover:bg-muted/60'
                            "
                            :aria-pressed="filtros.ruta === String(ruta.id)"
                            @click="seleccionarRuta(ruta.id)"
                        >
                            <div
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <span
                                    class="font-mono text-sm font-semibold text-foreground"
                                    >{{ ruta.codigo }}</span
                                >
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-medium text-foreground"
                                    :style="{
                                        backgroundColor: `${colors.azulClaro}22`,
                                    }"
                                >
                                    {{ visualRuta(ruta.estado).etiqueta }}
                                </span>
                            </div>
                            <p class="mt-1 text-xs text-muted-foreground">
                                {{ ruta.sucursal }} · {{ ruta.fecha }} ·
                                {{ ruta.paradas.length }} paradas
                            </p>
                            <div
                                class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground"
                            >
                                <span
                                    >Plan:
                                    {{ ruta.km_planificados ?? '—' }} km</span
                                >
                                <span
                                    >Recorrido:
                                    {{ ruta.km_recorridos ?? '—' }} km</span
                                >
                                <span
                                    >{{ ruta.incidencias }} incidencia(s) ·
                                    {{ ruta.minutos_retraso }} min de
                                    retraso</span
                                >
                            </div>
                        </button>
                    </div>
                    <p
                        v-else
                        class="py-8 text-center text-sm text-muted-foreground"
                    >
                        No hay recorridos con los filtros seleccionados.
                    </p>
                </div>

                <div
                    id="panel-estados"
                    role="tabpanel"
                    aria-labelledby="tab-estados"
                    :hidden="panelActivo !== 'estados'"
                    class="space-y-3 p-3 xl:min-h-0 xl:flex-1 xl:overflow-y-auto"
                >
                    <div>
                        <h2 class="font-semibold text-foreground">
                            Estados de los envíos
                        </h2>
                        <p class="mt-1 text-xs text-muted-foreground">
                            Filtra indicadores, mapa y análisis territorial.
                        </p>
                    </div>
                    <div v-if="datos" class="flex flex-wrap gap-2">
                        <button
                            v-for="estado in datos.estados"
                            :key="estado.estado"
                            type="button"
                            class="rounded-full border px-3 py-1.5 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            :class="
                                filtros.estado === estado.estado
                                    ? 'border-transparent text-white'
                                    : 'border-sidebar-border/70 bg-background text-foreground hover:bg-muted'
                            "
                            :style="
                                filtros.estado === estado.estado
                                    ? {
                                          backgroundColor: colorEstado(
                                              estado.estado,
                                          ),
                                      }
                                    : undefined
                            "
                            :aria-pressed="filtros.estado === estado.estado"
                            @click="
                                filtros.estado =
                                    filtros.estado === estado.estado
                                        ? ''
                                        : estado.estado
                            "
                        >
                            {{ etiquetaEstado(estado.estado) }}
                            <span class="ml-1 tabular-nums opacity-80">{{
                                estado.total
                            }}</span>
                        </button>
                    </div>
                </div>
            </aside>
        </div>
    </main>
</template>
