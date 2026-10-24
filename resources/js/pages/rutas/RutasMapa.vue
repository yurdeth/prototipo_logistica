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
import { fmtHora, visualRuta, type DatosMapa } from '@/config/Rutas/Mapa';

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
});
const datos = ref<DatosMapa | null>(null);
const error = ref<string | null>(null);
const cargando = ref(false);
const filtrosAbiertos = ref(false);
const indiceParadaActual = ref(0);
const mostrarConexiones = ref(false);
const mapaRef = ref<InstanceType<typeof MapaRutas> | null>(null);

type PanelId = 'recorridos' | 'territorios' | 'estados';
const panelActivo = ref<PanelId>('recorridos');
const panelesAnalisis: { id: PanelId; etiqueta: string }[] = [
    { id: 'recorridos', etiqueta: 'Recorridos' },
    { id: 'territorios', etiqueta: 'Territorios' },
    { id: 'estados', etiqueta: 'Estados' },
];
let solicitud: AbortController | null = null;

/* ───────── Carga de datos ───────── */
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
    void cargarDatos();
});

watch(
    () => filtros.ruta,
    (ruta) => {
        indiceParadaActual.value = 0;
        if (ruta) {
            mostrarConexiones.value = true;
        }
    },
);

/* ───────── Datos derivados ───────── */
const territoriosDisponibles = computed(
    () => datos.value?.catalogos.territorios ?? props.territorios,
);
const rutaSeleccionada = computed(
    () =>
        datos.value?.rutas.find((ruta) => String(ruta.id) === filtros.ruta) ??
        null,
);
const paradasRuta = computed(() => rutaSeleccionada.value?.paradas ?? []);
const paradaActual = computed(
    () => paradasRuta.value[indiceParadaActual.value] ?? null,
);

// Si al recargar la ruta tiene menos paradas, el índice no debe quedar fuera de rango.
watch(paradasRuta, (lista) => {
    if (indiceParadaActual.value > lista.length - 1) {
        indiceParadaActual.value = Math.max(lista.length - 1, 0);
    }
});

const avanzarParada = (direccion: -1 | 1): void => {
    indiceParadaActual.value = Math.min(
        Math.max(indiceParadaActual.value + direccion, 0),
        paradasRuta.value.length - 1,
    );
};

const etiquetaPosicion = computed(() => {
    const total = paradasRuta.value.length;
    if (!paradaActual.value || !total) return '';
    if (total === 1) return 'Única parada';
    if (indiceParadaActual.value === total - 1) return 'Destino final';
    if (indiceParadaActual.value === 0) return 'Inicio del recorrido';
    return 'Parada intermedia';
});

const progresoRuta = computed(() => {
    const total = paradasRuta.value.length;
    return total ? ((indiceParadaActual.value + 1) / total) * 100 : 0;
});

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
    { value: '7', label: '7 días' },
    { value: '14', label: '14 días' },
    { value: '30', label: '30 días' },
    { value: '90', label: '90 días' },
    { value: '0', label: 'Todo' },
];
const etiquetasPeriodo: Record<string, string> = {
    '1': 'Hoy',
    '7': 'Últimos 7 días',
    '14': 'Últimos 14 días',
    '30': 'Últimos 30 días',
    '90': 'Últimos 90 días',
    '0': 'Todo el historial',
};
const tituloPeriodo = computed(
    () => etiquetasPeriodo[filtros.dias] ?? 'Período seleccionado',
);

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

const colorEstado = (estado: string | null | undefined): string =>
    estadoInfo[(estado ?? '') as EstadoEnvio]?.color ?? colors.grisMedio;

/* ───────── Acciones ───────── */
const seleccionarRuta = (rutaId: number): void => {
    filtros.ruta = filtros.ruta === String(rutaId) ? '' : String(rutaId);
};

const limpiarFiltros = (): void => {
    filtros.dias = '7';
    filtros.estado = '';
    filtros.ruta = '';
    filtros.territorio = '';
};

type ClaveFiltro = 'dias' | 'estado' | 'ruta' | 'territorio';

// El período siempre está visible en la barra, por eso aquí solo van los filtros adicionales.
const filtrosActivos = computed(() => {
    const activos: { clave: ClaveFiltro; texto: string }[] = [];

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
    return activos;
});

const hayCambios = computed(
    () => filtrosActivos.value.length > 0 || filtros.dias !== '7',
);

const quitarFiltro = (clave: ClaveFiltro): void => {
    filtros[clave] = clave === 'dias' ? '7' : '';
};

const moverPestana = (evento: KeyboardEvent, indice: number): void => {
    const delta =
        evento.key === 'ArrowRight' ? 1 : evento.key === 'ArrowLeft' ? -1 : 0;
    if (!delta) return;

    evento.preventDefault();
    const total = panelesAnalisis.length;
    const siguiente = panelesAnalisis[(indice + delta + total) % total];
    panelActivo.value = siguiente.id;
    document.getElementById(`tab-${siguiente.id}`)?.focus();
};

const conteoPestana = (id: PanelId): number | null => {
    if (!datos.value) return null;
    if (id === 'recorridos') return datos.value.rutas.length;
    if (id === 'territorios') return datos.value.territorios.length;
    return null;
};

const recentrarMapa = (): void => {
    mapaRef.value?.ajustarVista();
};
</script>

<template>
    <Head title="Inteligencia geográfica de rutas" />

    <main
        class="flex h-full min-h-0 flex-1 flex-col gap-4 overflow-y-auto p-4 md:p-6"
    >
        <!-- Encabezado -->
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div class="min-w-0">
                <h1
                    class="text-2xl font-semibold tracking-tight text-foreground"
                >
                    Inteligencia geográfica de rutas
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    Desempeño, concentración de envíos y análisis operativo por
                    territorio.
                </p>
            </div>
            <button
                type="button"
                class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-sidebar-border/70 bg-background px-3 text-sm font-medium text-foreground transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-60"
                :disabled="cargando"
                @click="cargarDatos"
            >
                <svg
                    class="h-4 w-4"
                    :class="
                        cargando
                            ? 'animate-spin motion-reduce:animate-none'
                            : ''
                    "
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    aria-hidden="true"
                >
                    <path d="M21 12a9 9 0 1 1-3-6.7L21 8" />
                    <path d="M21 3v5h-5" />
                </svg>
                {{ cargando ? 'Actualizando…' : 'Actualizar' }}
            </button>
        </header>

        <!-- Barra de filtros: el período siempre a la vista -->
        <section
            class="rounded-xl border border-sidebar-border/70 bg-card shadow-sm"
            aria-label="Filtros del análisis geográfico"
        >
            <div class="flex flex-wrap items-center gap-3 p-3">
                <div
                    class="max-w-full overflow-x-auto"
                    role="group"
                    aria-label="Período"
                >
                    <div class="inline-flex gap-1 rounded-lg bg-muted/60 p-1">
                        <button
                            v-for="periodo in opcionesPeriodo"
                            :key="periodo.value"
                            type="button"
                            class="min-h-8 rounded-md px-3 text-sm font-medium whitespace-nowrap transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            :class="
                                filtros.dias === periodo.value
                                    ? 'bg-background text-foreground shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            :aria-pressed="filtros.dias === periodo.value"
                            @click="filtros.dias = periodo.value"
                        >
                            {{ periodo.label }}
                        </button>
                    </div>
                </div>

                <div class="ml-auto flex items-center gap-2">
                    <button
                        v-if="hayCambios"
                        type="button"
                        class="min-h-10 rounded-lg px-3 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        @click="limpiarFiltros"
                    >
                        Restablecer
                    </button>
                    <button
                        type="button"
                        class="inline-flex min-h-10 items-center gap-2 rounded-lg border border-sidebar-border/70 bg-background px-3 text-sm font-medium text-foreground transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        aria-controls="panel-filtros-mapa"
                        :aria-expanded="filtrosAbiertos"
                        @click="filtrosAbiertos = !filtrosAbiertos"
                    >
                        <span>Más filtros</span>
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

            <!-- Filtros aplicados (chips) -->
            <div
                v-if="filtrosActivos.length"
                class="flex flex-wrap items-center gap-2 border-t border-sidebar-border/70 px-3 py-2"
            >
                <span class="text-xs text-muted-foreground">Aplicados:</span>
                <button
                    v-for="filtro in filtrosActivos"
                    :key="filtro.clave"
                    type="button"
                    class="inline-flex min-h-7 items-center gap-1 rounded-full border border-sidebar-border/70 bg-muted/50 px-2.5 text-xs text-foreground transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    :aria-label="`Quitar filtro ${filtro.texto}`"
                    @click="quitarFiltro(filtro.clave)"
                >
                    {{ filtro.texto }}
                    <span aria-hidden="true" class="text-muted-foreground"
                        >×</span
                    >
                </button>
            </div>

            <div
                v-if="filtrosAbiertos"
                id="panel-filtros-mapa"
                class="grid gap-3 border-t border-sidebar-border/70 p-4 sm:grid-cols-3"
            >
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
            </div>
        </section>

        <!-- Error con opción de reintento -->
        <div
            v-if="error"
            role="alert"
            class="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-red-300 bg-red-50 px-4 py-3 text-sm text-red-800 dark:border-red-900 dark:bg-red-950/40 dark:text-red-200"
        >
            <p>{{ error }}</p>
            <button
                type="button"
                class="min-h-9 rounded-lg border border-current px-3 font-medium transition-colors hover:bg-red-100 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none dark:hover:bg-red-950"
                @click="cargarDatos"
            >
                Reintentar
            </button>
        </div>

        <!-- Indicadores: siempre visibles arriba -->
        <section
            class="grid grid-cols-2 gap-3 transition-opacity sm:grid-cols-3 xl:grid-cols-6"
            :class="cargando && datos ? 'opacity-70' : ''"
            aria-label="Indicadores de desempeño"
        >
            <template v-if="datos">
                <article
                    v-for="stat in estadisticas"
                    :key="stat.label"
                    class="relative overflow-hidden rounded-xl border border-sidebar-border/70 bg-card py-3 pr-3 pl-4 shadow-sm"
                >
                    <span
                        class="absolute inset-y-0 left-0 w-1"
                        :style="{ backgroundColor: stat.color }"
                        aria-hidden="true"
                    />
                    <p class="text-xs font-medium text-muted-foreground">
                        {{ stat.label }}
                    </p>
                    <p
                        class="mt-1 text-2xl font-semibold tracking-tight text-foreground tabular-nums"
                    >
                        {{ stat.valor }}
                    </p>
                </article>
            </template>
            <template v-else>
                <div
                    v-for="n in 6"
                    :key="n"
                    class="h-[74px] animate-pulse rounded-xl border border-sidebar-border/70 bg-muted/40 motion-reduce:animate-none"
                />
            </template>
        </section>

        <!-- Mapa + análisis -->
        <div
            class="grid gap-4 xl:h-[max(620px,calc(100vh-20rem))] xl:grid-cols-12"
        >
            <section
                class="flex flex-col overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm xl:col-span-8 xl:min-h-0"
            >
                <!-- Barra de herramientas del mapa -->
                <div
                    class="flex flex-wrap items-center gap-x-4 gap-y-2 border-b border-sidebar-border/70 px-4 py-3"
                >
                    <div class="min-w-0 flex-1 basis-48">
                        <h2 class="font-semibold text-foreground">
                            Distribución geográfica
                        </h2>
                        <p class="mt-0.5 text-xs text-muted-foreground">
                            {{ tituloPeriodo }} · {{ enviosConUbicacion }}
                            envíos con ubicación
                        </p>
                    </div>

                    <label class="flex items-center gap-2 text-sm">
                        <span class="text-muted-foreground">Ruta</span>
                        <select
                            v-model="filtros.ruta"
                            class="h-9 w-44 max-w-full rounded-lg border border-sidebar-border/70 bg-background px-2 text-sm"
                        >
                            <option value="">Todas las rutas</option>
                            <option
                                v-for="ruta in datos?.catalogos.rutas ?? []"
                                :key="ruta.id"
                                :value="String(ruta.id)"
                            >
                                {{ ruta.codigo }} · {{ ruta.fecha }}
                            </option>
                        </select>
                    </label>

                    <button
                        type="button"
                        role="switch"
                        :aria-checked="mostrarConexiones"
                        class="inline-flex min-h-9 items-center gap-2 rounded-lg px-2 text-sm text-foreground transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        @click="mostrarConexiones = !mostrarConexiones"
                    >
                        <span
                            class="relative h-5 w-9 shrink-0 rounded-full transition-colors"
                            :class="
                                mostrarConexiones
                                    ? 'bg-primary'
                                    : 'bg-muted-foreground/30'
                            "
                            aria-hidden="true"
                        >
                            <span
                                class="absolute top-0.5 left-0.5 h-4 w-4 rounded-full bg-white shadow transition-transform"
                                :class="
                                    mostrarConexiones ? 'translate-x-4' : ''
                                "
                            />
                        </span>
                        Conexiones
                    </button>

                    <button
                        type="button"
                        class="inline-flex min-h-9 items-center gap-2 rounded-lg border border-sidebar-border/70 bg-background px-3 text-sm font-medium text-foreground transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        :disabled="!datos"
                        @click="recentrarMapa"
                    >
                        <svg
                            class="h-4 w-4"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="2"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                        >
                            <circle cx="12" cy="12" r="3" />
                            <path d="M12 2v4M12 18v4M2 12h4M18 12h4" />
                        </svg>
                        Recentrar
                    </button>
                </div>

                <!-- Mapa con controles flotantes -->
                <div
                    class="relative h-[62vh] min-h-[420px] xl:h-auto xl:min-h-0 xl:flex-1"
                >
                    <div v-if="datos" class="absolute inset-0">
                        <MapaRutas
                            ref="mapaRef"
                            :sucursales="datos.sucursales"
                            :despachos="datos.rutas"
                            :envios="datos.envios"
                            :seleccionado="
                                filtros.ruta ? Number(filtros.ruta) : null
                            "
                            :parada-seleccionada-id="
                                paradaActual?.envio_id ?? null
                            "
                            :mostrar-conexiones="mostrarConexiones"
                            @seleccionar="seleccionarRuta"
                        />
                    </div>
                    <div
                        v-else
                        class="absolute inset-0 flex items-center justify-center px-6 text-center text-sm text-muted-foreground"
                    >
                        {{
                            cargando
                                ? 'Cargando información geográfica…'
                                : 'No hay información para mostrar.'
                        }}
                    </div>

                    <!-- Indicador de recarga sobre el mapa -->
                    <div
                        v-if="cargando && datos"
                        class="pointer-events-none absolute top-14 left-1/2 z-[1100] -translate-x-1/2 rounded-full border border-sidebar-border/70 bg-card/95 px-3 py-1 text-xs text-muted-foreground shadow-sm"
                        role="status"
                    >
                        Actualizando…
                    </div>

                    <!-- Parada actual: tarjeta flotante con navegación -->
                    <div
                        v-if="paradaActual"
                        class="absolute inset-x-3 bottom-7 z-[1000] mx-auto max-w-2xl rounded-xl border border-sidebar-border/70 bg-card/95 p-3 shadow-lg backdrop-blur"
                        aria-label="Información de la parada seleccionada"
                    >
                        <div class="flex items-center gap-2">
                            <button
                                type="button"
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-sidebar-border/70 bg-background text-foreground transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-40"
                                aria-label="Parada anterior"
                                :disabled="indiceParadaActual === 0"
                                @click="avanzarParada(-1)"
                            >
                                <svg
                                    class="h-4 w-4"
                                    viewBox="0 0 20 20"
                                    fill="none"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="m12 5-5 5 5 5"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                </svg>
                            </button>

                            <div
                                class="min-w-0 flex-1 text-center"
                                role="status"
                                aria-live="polite"
                            >
                                <p
                                    class="text-sm font-semibold text-foreground tabular-nums"
                                >
                                    Parada {{ indiceParadaActual + 1 }} de
                                    {{ paradasRuta.length }}
                                </p>
                                <p class="text-xs text-muted-foreground">
                                    {{ etiquetaPosicion }}
                                </p>
                            </div>

                            <button
                                type="button"
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-sidebar-border/70 bg-background text-foreground transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-40"
                                aria-label="Siguiente parada"
                                :disabled="
                                    indiceParadaActual >= paradasRuta.length - 1
                                "
                                @click="avanzarParada(1)"
                            >
                                <svg
                                    class="h-4 w-4"
                                    viewBox="0 0 20 20"
                                    fill="none"
                                    aria-hidden="true"
                                >
                                    <path
                                        d="m8 5 5 5-5 5"
                                        stroke="currentColor"
                                        stroke-width="1.8"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                </svg>
                            </button>
                        </div>

                        <div
                            class="mt-2 h-1 overflow-hidden rounded-full bg-muted"
                            aria-hidden="true"
                        >
                            <div
                                class="h-full rounded-full bg-primary transition-[width] duration-300 motion-reduce:transition-none"
                                :style="{ width: `${progresoRuta}%` }"
                            />
                        </div>

                        <div
                            class="mt-3 flex items-start justify-between gap-3"
                        >
                            <div class="min-w-0">
                                <p
                                    class="truncate text-sm font-semibold text-foreground"
                                >
                                    {{ paradaActual.codigo_guia }} ·
                                    {{ paradaActual.destino }}
                                </p>
                                <p
                                    class="truncate text-xs text-muted-foreground"
                                >
                                    {{
                                        paradaActual.direccion ||
                                        'Sin dirección registrada'
                                    }}
                                </p>
                            </div>
                            <span
                                class="shrink-0 rounded-full border px-2 py-0.5 text-xs font-medium text-foreground"
                                :style="{
                                    backgroundColor: `${colorEstado(paradaActual.estado_envio)}33`,
                                    borderColor: colorEstado(
                                        paradaActual.estado_envio,
                                    ),
                                }"
                            >
                                {{
                                    paradaActual.estado_envio
                                        ? etiquetaEstado(
                                              paradaActual.estado_envio,
                                          )
                                        : 'Sin estado'
                                }}
                            </span>
                        </div>

                        <p
                            class="mt-2 flex flex-wrap gap-x-4 gap-y-0.5 text-xs text-muted-foreground"
                        >
                            <span
                                >Estimada:
                                <strong class="font-medium text-foreground">{{
                                    fmtHora(paradaActual.hora_estimada)
                                }}</strong></span
                            >
                            <span
                                >Real:
                                <strong class="font-medium text-foreground">{{
                                    paradaActual.hora_real
                                        ? fmtHora(paradaActual.hora_real)
                                        : 'Pendiente'
                                }}</strong></span
                            >
                            <span
                                >Incidencias:
                                <strong class="font-medium text-foreground">{{
                                    paradaActual.incidencias
                                }}</strong></span
                            >
                        </p>

                        <p
                            v-if="paradaActual.ubicacion_aproximada"
                            class="mt-2 text-xs font-medium text-amber-700 dark:text-amber-400"
                        >
                            Ubicación aproximada: referencia territorial o de
                            sucursales; no es dirección exacta ni GPS.
                        </p>
                        <p
                            v-else-if="
                                paradaActual.lat === null ||
                                paradaActual.lng === null
                            "
                            class="mt-2 text-xs font-medium text-destructive"
                        >
                            Esta parada no tiene coordenadas ni referencia
                            territorial, por eso no aparece en el mapa.
                        </p>
                    </div>

                    <!-- Sugerencia cuando todavía no hay ruta elegida -->
                    <p
                        v-else-if="datos?.rutas.length"
                        class="pointer-events-none absolute bottom-7 left-1/2 z-[1000] w-max max-w-[calc(100%-1.5rem)] -translate-x-1/2 rounded-full border border-sidebar-border/70 bg-card/95 px-4 py-2 text-center text-xs text-muted-foreground shadow-md"
                    >
                        Elige una ruta para recorrer sus paradas una a una.
                    </p>
                </div>

                <p
                    v-if="datos && enviosConUbicacion < datos.kpis.total"
                    class="border-t border-sidebar-border/70 px-4 py-2 text-xs text-muted-foreground"
                >
                    {{ datos.kpis.total - enviosConUbicacion }} envío(s) no
                    tienen coordenadas reportadas en sus eventos. El seguimiento
                    puede usar una referencia aproximada calculada a partir de
                    las ubicaciones de sucursales del territorio.
                </p>
            </section>

            <!-- Panel de análisis -->
            <aside
                class="flex max-h-[70vh] flex-col overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm xl:col-span-4 xl:max-h-none xl:min-h-0"
                aria-label="Análisis de rutas"
            >
                <div
                    class="grid shrink-0 grid-cols-3 gap-1 border-b border-sidebar-border/70 p-1"
                    role="tablist"
                    aria-label="Secciones del análisis"
                >
                    <button
                        v-for="(pestana, indice) in panelesAnalisis"
                        :id="`tab-${pestana.id}`"
                        :key="pestana.id"
                        type="button"
                        role="tab"
                        class="flex min-h-10 items-center justify-center gap-1.5 rounded-lg px-2 text-sm font-medium transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        :class="
                            panelActivo === pestana.id
                                ? 'bg-primary text-primary-foreground'
                                : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                        "
                        :aria-selected="panelActivo === pestana.id"
                        :aria-controls="`panel-${pestana.id}`"
                        :tabindex="panelActivo === pestana.id ? 0 : -1"
                        @click="panelActivo = pestana.id"
                        @keydown="moverPestana($event, indice)"
                    >
                        {{ pestana.etiqueta }}
                        <span
                            v-if="conteoPestana(pestana.id) !== null"
                            class="rounded-full px-1.5 text-xs tabular-nums"
                            :class="
                                panelActivo === pestana.id
                                    ? 'bg-primary-foreground/20'
                                    : 'bg-muted'
                            "
                            >{{ conteoPestana(pestana.id) }}</span
                        >
                    </button>
                </div>

                <!-- Recorridos -->
                <div
                    id="panel-recorridos"
                    role="tabpanel"
                    aria-labelledby="tab-recorridos"
                    :hidden="panelActivo !== 'recorridos'"
                    class="min-h-0 flex-1 space-y-3 overflow-y-auto p-3"
                >
                    <p class="text-xs text-muted-foreground">
                        Selecciona un despacho para verlo en el mapa y recorrer
                        sus paradas.
                    </p>
                    <ul v-if="datos?.rutas.length" class="space-y-2">
                        <li v-for="ruta in datos.rutas" :key="ruta.id">
                            <button
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
                                        class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-xs font-medium text-foreground"
                                        :style="{
                                            backgroundColor: `${visualRuta(ruta.estado).color}26`,
                                        }"
                                    >
                                        <span
                                            class="h-2 w-2 rounded-full"
                                            :style="{
                                                backgroundColor: visualRuta(
                                                    ruta.estado,
                                                ).color,
                                            }"
                                            aria-hidden="true"
                                        />
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
                                        {{ ruta.km_planificados ?? '—' }}
                                        km</span
                                    >
                                    <span
                                        >Recorrido:
                                        {{ ruta.km_recorridos ?? '—' }}
                                        km</span
                                    >
                                    <span
                                        >{{ ruta.incidencias }} incidencia(s) ·
                                        {{ ruta.minutos_retraso }} min de
                                        retraso</span
                                    >
                                </div>
                            </button>

                            <!-- Paradas de la ruta elegida: clic para ir a cada una -->
                            <ol
                                v-if="
                                    filtros.ruta === String(ruta.id) &&
                                    ruta.paradas.length
                                "
                                class="mt-2 ml-4 space-y-0.5 border-l border-sidebar-border/70 pl-3"
                                aria-label="Paradas de la ruta"
                            >
                                <li
                                    v-for="(parada, i) in ruta.paradas"
                                    :key="parada.envio_id"
                                >
                                    <button
                                        type="button"
                                        class="flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left text-xs transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        :class="
                                            i === indiceParadaActual
                                                ? 'bg-primary/10 font-semibold text-foreground'
                                                : 'text-muted-foreground hover:bg-muted hover:text-foreground'
                                        "
                                        :aria-current="
                                            i === indiceParadaActual
                                                ? 'step'
                                                : undefined
                                        "
                                        @click="indiceParadaActual = i"
                                    >
                                        <span
                                            class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 bg-white text-[10px] font-bold text-slate-800"
                                            :style="{
                                                borderColor: colorEstado(
                                                    parada.estado_envio,
                                                ),
                                            }"
                                            >{{ parada.orden }}</span
                                        >
                                        <span class="min-w-0 flex-1 truncate"
                                            >{{ parada.codigo_guia }} ·
                                            {{ parada.destino }}</span
                                        >
                                        <span
                                            v-if="parada.ubicacion_aproximada"
                                            class="shrink-0 text-amber-600"
                                            title="Ubicación aproximada"
                                            >≈</span
                                        >
                                        <span
                                            v-else-if="
                                                parada.lat === null ||
                                                parada.lng === null
                                            "
                                            class="shrink-0 font-bold text-destructive"
                                            title="Sin ubicación en el mapa"
                                            >!</span
                                        >
                                    </button>
                                </li>
                            </ol>
                        </li>
                    </ul>
                    <p
                        v-else
                        class="py-8 text-center text-sm text-muted-foreground"
                    >
                        No hay recorridos con los filtros seleccionados.
                    </p>
                </div>

                <!-- Territorios -->
                <div
                    id="panel-territorios"
                    role="tabpanel"
                    aria-labelledby="tab-territorios"
                    :hidden="panelActivo !== 'territorios'"
                    class="min-h-0 flex-1 space-y-4 overflow-y-auto p-3"
                >
                    <p class="text-xs text-muted-foreground">
                        Volumen y entregas por territorio, con los filtros
                        aplicados.
                    </p>
                    <p
                        v-if="datos?.territorios.length"
                        class="rounded-lg border border-sidebar-border/70 bg-muted/30 p-3 text-sm text-muted-foreground"
                    >
                        Mayor volumen:
                        <strong class="text-foreground">{{
                            datos.territorios[0].nombre
                        }}</strong>
                        · {{ datos.territorios[0].total }} envíos
                    </p>
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
                </div>

                <!-- Estados -->
                <div
                    id="panel-estados"
                    role="tabpanel"
                    aria-labelledby="tab-estados"
                    :hidden="panelActivo !== 'estados'"
                    class="min-h-0 flex-1 space-y-3 overflow-y-auto p-3"
                >
                    <p class="text-xs text-muted-foreground">
                        Toca un estado para filtrar indicadores, mapa y análisis
                        territorial. Vuelve a tocarlo para quitar el filtro.
                    </p>
                    <div
                        v-if="datos?.estados.length"
                        class="flex flex-wrap gap-2"
                    >
                        <button
                            v-for="estado in datos.estados"
                            :key="estado.estado"
                            type="button"
                            class="inline-flex min-h-9 items-center gap-2 rounded-full border px-3 text-sm transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                            :class="
                                filtros.estado === estado.estado
                                    ? 'border-primary bg-primary/10 font-medium text-foreground'
                                    : 'border-sidebar-border/70 bg-background text-foreground hover:bg-muted'
                            "
                            :aria-pressed="filtros.estado === estado.estado"
                            @click="
                                filtros.estado =
                                    filtros.estado === estado.estado
                                        ? ''
                                        : estado.estado
                            "
                        >
                            <span
                                class="h-2.5 w-2.5 rounded-full"
                                :style="{
                                    backgroundColor: colorEstado(estado.estado),
                                }"
                                aria-hidden="true"
                            />
                            {{ etiquetaEstado(estado.estado) }}
                            <span class="text-muted-foreground tabular-nums">{{
                                estado.total
                            }}</span>
                        </button>
                    </div>
                    <p
                        v-else
                        class="py-8 text-center text-sm text-muted-foreground"
                    >
                        No hay estados para los filtros seleccionados.
                    </p>
                </div>
            </aside>
        </div>
    </main>
</template>
