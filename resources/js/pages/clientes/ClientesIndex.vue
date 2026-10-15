<script setup lang="ts">
import { computed, onBeforeUnmount, reactive, ref } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { colors } from '@/UI/Colors';
import { MapPin } from '@/UI/Icons';
import { dashboard } from '@/routes';
import { fmtFecha } from '@/composables/useEnviosBI';

import EncabezadoAcciones from '@/components/bi/BiHeader.vue';
import StatCards from '@/components/bi/StatCards.vue';
import BiFilters from '@/components/bi/BiFilters.vue';
import BiChips from '@/components/bi/StatusChips.vue';
import BiTable from '@/components/bi/BiTable.vue';
import BiTableRow from '@/components/bi/BiTableRow.vue';
import BiPagination from '@/components/bi/BiPagination.vue';
import BiBadge from '@/components/bi/BiBadge.vue';
import BiPanelHead from '@/components/bi/BiPanelHead.vue';
import BiBarList from '@/components/bi/BiBarList.vue';
import ConfirmDialog from '@/components/Forms/ConfirmDialog.vue';
import {
    soft,
    type Activo,
    type BarRow,
    type Chip,
    type Columna,
    type Stat,
} from '@/components/bi/Utils';

type Tipo = 'PERSONA' | 'EMPRESA';

/* ───────── Tipos de lo que envía ClienteController@index ───────── */
interface FilaCliente {
    id_cliente: number;
    nombre: string;
    tipo_cliente: Tipo;
    contacto: string | null;
    territorio: string;
    total_envios: number;
    created_at: string | null;
    // NUEVOS (opcionales): fecha del último envío y si el cliente está inactivo.
    ultimo_envio?: string | null;
    inactivo?: boolean;
}

interface Paginado<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
}

interface Filtros {
    q: string | null;
    tipo: Tipo | null;
    territorio: number | null;
    inactivos?: boolean | null; // NUEVO: solo clientes inactivos
}

interface ClienteTop {
    id_cliente: number;
    nombre: string;
    total_envios: number;
}

const props = withDefaults(
    defineProps<{
        clientes: Paginado<FilaCliente>;
        filtros: Filtros;
        catalogos: { territorios: { id: number; nombre: string }[] };
        resumen: {
            kpis: {
                total: number;
                empresas: number;
                personas: number;
                con_envios: number;
                // NUEVOS (opcionales)
                nuevos?: number; // altas de los últimos 30 días
                nuevos_previos?: number; // altas de los 30 días anteriores
                inactivos?: number; // con envíos, pero ninguno en DIAS_INACTIVO días
            };
            tipos: { tipo: Tipo; total: number }[];
        };
        // NUEVO (opcional): análisis de concentración para el panel Top clientes.
        analisis?: { top: ClienteTop[]; envios_totales: number };
        permisos: { registrar: boolean; editar: boolean; eliminar: boolean };
    }>(),
    { analisis: () => ({ top: [], envios_totales: 0 }) },
);

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Clientes', href: '#' },
        ],
    },
});

const TIPOS: Record<Tipo, { label: string; plural: string; color: string }> = {
    EMPRESA: { label: 'Empresa', plural: 'Empresas', color: colors.azulClaro },
    PERSONA: { label: 'Persona', plural: 'Personas', color: '#64748b' },
};

// Deben coincidir con la definición que usa el backend.
const DIAS_INACTIVO = 90; // sin envíos en este periodo = inactivo
const UMBRAL_INACTIVOS = 20; // % de clientes con envíos a partir del cual se alerta

/* ───────── Consulta al servidor ───────── */
const f = reactive<Filtros>({ ...props.filtros });
const busqueda = ref(props.filtros.q ?? '');

// Solo se envían los filtros que tienen valor, para mantener la URL limpia.
const parametros = (): Record<string, string | number> => {
    const params: Record<string, string | number> = {};

    if (busqueda.value.trim()) params.q = busqueda.value.trim();
    if (f.tipo) params.tipo = f.tipo;
    if (f.territorio) params.territorio = f.territorio;
    if (f.inactivos) params.inactivos = 1;

    return params;
};

const consultar = (pagina = 1) => {
    const params = parametros();
    if (pagina > 1) params.page = pagina;

    router.get('/clientes', params, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

// Descarga un CSV con TODOS los clientes que cumplen los filtros (no solo la página).
const exportar = () => {
    const qs = new URLSearchParams(
        Object.entries(parametros()).map(([k, v]) => [k, String(v)]),
    ).toString();

    window.location.href = `/clientes/exportar${qs ? `?${qs}` : ''}`;
};

// La búsqueda espera a que se deje de escribir para no consultar en cada tecla.
let temporizador: ReturnType<typeof setTimeout> | undefined;
const alBuscar = (valor: string) => {
    busqueda.value = valor;
    clearTimeout(temporizador);
    temporizador = setTimeout(() => consultar(), 350);
};
onBeforeUnmount(() => clearTimeout(temporizador));

const alTipo = (k: string) => {
    f.tipo = f.tipo === k ? null : (k as Tipo);
    consultar();
};

const alTerritorio = (e: Event) => {
    const v = (e.target as HTMLSelectElement).value;
    f.territorio = v ? Number(v) : null;
    consultar();
};

const alInactivos = () => {
    f.inactivos = !f.inactivos;
    consultar();
};

const page = computed({
    get: () => props.clientes.current_page,
    set: (n: number) => consultar(n),
});

const hayFiltros = computed(
    () => !!(busqueda.value || f.tipo || f.territorio || f.inactivos),
);

const limpiar = () => {
    clearTimeout(temporizador);
    busqueda.value = '';
    Object.assign(f, {
        q: null,
        tipo: null,
        territorio: null,
        inactivos: null,
    });
    consultar();
};

const activos = computed<Activo[]>(() => {
    const lista: Activo[] = [];

    if (f.tipo)
        lista.push({ key: 'tipo', label: `Tipo: ${TIPOS[f.tipo].label}` });
    if (f.territorio) {
        const t = props.catalogos.territorios.find(
            (x) => x.id === f.territorio,
        );
        lista.push({
            key: 'territorio',
            label: `Territorio: ${t?.nombre ?? f.territorio}`,
        });
    }
    if (f.inactivos) {
        lista.push({
            key: 'inactivos',
            label: `Inactivos (${DIAS_INACTIVO}+ días)`,
        });
    }

    return lista;
});

const quitarFiltro = (key: string) => {
    if (key === 'tipo') f.tipo = null;
    if (key === 'territorio') f.territorio = null;
    if (key === 'inactivos') f.inactivos = null;
    consultar();
};

/* ───────── Tarjetas y fichas ───────── */
const k = computed(() => props.resumen.kpis);

const num = (v?: number) => (v === undefined ? '—' : v.toLocaleString('es-SV'));

const variacion = (actual?: number, previo?: number) => {
    if (actual === undefined || previo === undefined || previo === 0) {
        return { texto: 'Sin datos previos', baja: false };
    }

    const dif = ((actual - previo) / previo) * 100;

    if (Math.abs(dif) < 0.5) {
        return { texto: 'Sin cambios vs. 30 días previos', baja: false };
    }

    return {
        texto: `${dif > 0 ? '+' : ''}${dif.toFixed(0)}% vs. 30 días previos`,
        baja: dif < 0,
    };
};

// El color solo aparece cuando el indicador es una señal de alerta.
const tarjetas = computed<Stat[]>(() => {
    const nuevos = variacion(k.value.nuevos, k.value.nuevos_previos);
    const inactivos = k.value.inactivos;
    const pctInactivos =
        inactivos !== undefined && k.value.con_envios
            ? (inactivos / k.value.con_envios) * 100
            : null;

    return [
        {
            label: 'Clientes',
            value: k.value.total.toLocaleString('es-SV'),
            hint: hayFiltros.value
                ? 'Con los filtros actuales'
                : 'Registrados en el sistema',
        },
        {
            label: 'Nuevos (30 días)',
            value: num(k.value.nuevos),
            hint: nuevos.texto,
            color: nuevos.baja ? colors.rojo : undefined,
        },
        {
            label: 'Inactivos',
            value: num(inactivos),
            hint:
                pctInactivos === null
                    ? `Sin envíos en ${DIAS_INACTIVO}+ días`
                    : `${pctInactivos.toFixed(0)}% de los clientes con envíos`,
            color:
                pctInactivos !== null && pctInactivos >= UMBRAL_INACTIVOS
                    ? colors.rojo
                    : undefined,
        },
        {
            label: 'Con envíos',
            value: k.value.con_envios,
            hint: k.value.total
                ? `${Math.round((k.value.con_envios / k.value.total) * 100)}% del total`
                : 'Sin clientes',
        },
    ];
});

const chips = computed<Chip[]>(() =>
    props.resumen.tipos.map((t) => ({
        key: t.tipo,
        label: TIPOS[t.tipo].plural,
        count: t.total,
        color: TIPOS[t.tipo].color,
    })),
);

/* ───────── Tabla ───────── */
const columnas: Columna[] = [
    { label: 'Cliente' },
    { label: 'Tipo' },
    { label: 'Territorio' },
    { label: 'Envíos' },
    { label: 'Último envío' },
    { label: 'Acciones' },
];

const editar = (c: FilaCliente) => {
    if (props.permisos.editar) router.visit(`/clientes/${c.id_cliente}/editar`);
};

const inicialesCliente = (nombre: string): string => {
    const iniciales = nombre
        .trim()
        .split(/\s+/)
        .slice(0, 2)
        .map((palabra) => palabra.charAt(0))
        .join('')
        .toUpperCase();

    return iniciales || 'CL';
};

// Recencia en lenguaje natural: "Hoy", "Hace 12 días", "Hace 4 meses".
const textoUltimo = (fecha?: string | null): string => {
    if (!fecha) return 'Sin envíos';

    const dias = Math.floor(
        (Date.now() - new Date(fecha).getTime()) / 86_400_000,
    );

    if (dias <= 0) return 'Hoy';
    if (dias === 1) return 'Ayer';
    if (dias < 60) return `Hace ${dias} días`;

    return `Hace ${Math.floor(dias / 30)} meses`;
};

/* ───────── Top clientes (concentración de envíos) ───────── */
const topTotal = computed(() =>
    props.analisis.top.reduce((a, t) => a + t.total_envios, 0),
);

const concentracion = computed(() =>
    props.analisis.envios_totales
        ? (topTotal.value / props.analisis.envios_totales) * 100
        : null,
);

const filasTop = computed<BarRow[]>(() => {
    const maximo = Math.max(
        1,
        ...props.analisis.top.map((t) => t.total_envios),
    );

    return props.analisis.top.map((t, i) => ({
        key: String(t.id_cliente),
        label: t.nombre,
        dot: colors.azulClaro,
        width: (t.total_envios / maximo) * 100,
        // Los tres primeros resaltan; el resto queda en tono suave.
        color: i < 3 ? colors.azulClaro : soft(colors.azulClaro, '59'),
        detail: props.analisis.envios_totales
            ? `${t.total_envios.toLocaleString('es-SV')} (${((t.total_envios / props.analisis.envios_totales) * 100).toFixed(1)}%)`
            : t.total_envios.toLocaleString('es-SV'),
    }));
});

const lecturaTop = computed(() => {
    if (concentracion.value === null) return '';

    return concentracion.value >= 50
        ? 'Alta dependencia: conviene asegurar la relación con estos clientes y diversificar la cartera.'
        : 'Cartera diversificada: ningún grupo pequeño de clientes concentra la operación.';
});

const abrirTop = (id: string) => {
    if (props.permisos.editar) router.visit(`/clientes/${id}/editar`);
};

/* ───────── Eliminar ───────── */
const aEliminar = ref<FilaCliente | null>(null);
const dialogo = ref(false);
const eliminando = ref(false);
const errorEliminar = ref<string | null>(null);

const pedirEliminar = (c: FilaCliente) => {
    aEliminar.value = c;
    errorEliminar.value = null;
    dialogo.value = true;
};

const confirmarEliminar = () => {
    const c = aEliminar.value;
    if (!c) return;

    router.delete(`/clientes/${c.id_cliente}`, {
        preserveScroll: true,
        onStart: () => (eliminando.value = true),
        onSuccess: () => {
            dialogo.value = false;
            aEliminar.value = null;
        },
        onError: (errores) => {
            errorEliminar.value =
                errores.eliminar ?? 'No se pudo eliminar el cliente.';
        },
        onFinish: () => (eliminando.value = false),
    });
};

const campoClase =
    'h-10 rounded-lg border border-sidebar-border/70 bg-card px-3 text-sm text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';
</script>

<template>
    <Head title="Clientes" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
        <!-- Encabezado -->
        <EncabezadoAcciones
            title="Clientes"
            subtitle="Personas y empresas que envían o reciben paquetes"
            accion="Nuevo cliente"
            href="/clientes/crear"
            @exportar="exportar"
        />

        <!-- Indicadores del conjunto filtrado. -->
        <StatCards :items="tarjetas" :cols="4" />

        <!-- Listado principal. -->
        <section
            class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm"
            aria-label="Listado de clientes"
        >
            <BiFilters
                :busqueda="busqueda"
                placeholder="Buscar por nombre o contacto"
                sin-alertas
                :activos="activos"
                :hay-filtros="hayFiltros"
                @update:busqueda="alBuscar"
                @quitar="quitarFiltro"
                @limpiar="limpiar"
            >
                <template #extra>
                    <select
                        :class="[campoClase, 'w-full sm:w-auto']"
                        aria-label="Territorio"
                        :value="f.territorio ?? ''"
                        @change="alTerritorio"
                    >
                        <option value="">Todos los territorios</option>
                        <option
                            v-for="t in catalogos.territorios"
                            :key="t.id"
                            :value="t.id"
                        >
                            {{ t.nombre }}
                        </option>
                    </select>

                    <button
                        type="button"
                        :class="[campoClase, 'w-full font-medium sm:w-auto']"
                        :style="
                            f.inactivos
                                ? {
                                      borderColor: colors.azulClaro,
                                      color: colors.azulClaro,
                                  }
                                : undefined
                        "
                        :aria-pressed="!!f.inactivos"
                        :title="`Clientes sin envíos en los últimos ${DIAS_INACTIVO} días`"
                        @click="alInactivos"
                    >
                        Solo inactivos
                    </button>
                </template>

                <BiChips :items="chips" :active="f.tipo" @toggle="alTipo" />
            </BiFilters>

            <BiTable
                :columns="columnas"
                :vacio="!clientes.data.length"
                empty-title="No se encontraron clientes"
                empty-hint="Prueba con otra búsqueda o registra un cliente nuevo."
            >
                <BiTableRow
                    v-for="c in clientes.data"
                    :key="c.id_cliente"
                    :selectable="permisos.editar"
                    @select="editar(c)"
                >
                    <td class="px-5 py-4">
                        <div class="flex min-w-[220px] items-center gap-3">
                            <span
                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl text-xs font-bold ring-1 ring-inset"
                                :class="
                                    c.tipo_cliente === 'EMPRESA'
                                        ? 'bg-blue-50 text-blue-700 ring-blue-100 dark:bg-blue-950/50 dark:text-blue-200 dark:ring-blue-900'
                                        : 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700'
                                "
                                aria-hidden="true"
                            >
                                {{ inicialesCliente(c.nombre) }}
                            </span>
                            <div class="min-w-0">
                                <p
                                    class="truncate text-sm font-semibold text-foreground"
                                >
                                    {{ c.nombre }}
                                </p>
                                <p
                                    class="mt-1 truncate text-xs text-muted-foreground"
                                >
                                    {{ c.contacto || 'Sin contacto' }}
                                </p>
                            </div>
                        </div>
                    </td>

                    <td class="px-5 py-4">
                        <BiBadge
                            :color="TIPOS[c.tipo_cliente].color"
                            :label="TIPOS[c.tipo_cliente].label"
                        />
                    </td>

                    <td class="px-5 py-4">
                        <div
                            class="flex min-w-[140px] items-center gap-2 text-sm text-foreground"
                        >
                            <MapPin
                                class="h-4 w-4 shrink-0 text-muted-foreground"
                                aria-hidden="true"
                            />
                            <span class="truncate">{{ c.territorio }}</span>
                        </div>
                    </td>

                    <td class="px-5 py-4">
                        <span
                            class="inline-flex min-w-10 justify-center rounded-full bg-muted px-3 py-1 text-sm font-semibold text-foreground tabular-nums"
                            :aria-label="`${c.total_envios} envíos`"
                        >
                            {{ c.total_envios.toLocaleString('es-SV') }}
                        </span>
                    </td>

                    <!-- Recencia: el dato que permite detectar clientes que se enfrían. -->
                    <td class="px-5 py-4 whitespace-nowrap">
                        <div class="flex items-center gap-2">
                            <span
                                class="text-sm"
                                :class="
                                    c.ultimo_envio
                                        ? 'text-foreground'
                                        : 'text-muted-foreground'
                                "
                                :title="
                                    c.ultimo_envio
                                        ? fmtFecha(c.ultimo_envio)
                                        : undefined
                                "
                            >
                                {{ textoUltimo(c.ultimo_envio) }}
                            </span>
                            <BiBadge
                                v-if="c.inactivo"
                                :color="colors.rojo"
                                label="Inactivo"
                            />
                        </div>
                    </td>

                    <td class="px-5 py-4 whitespace-nowrap">
                        <div
                            class="flex items-center gap-2 text-sm font-medium"
                        >
                            <button
                                v-if="permisos.editar"
                                type="button"
                                class="rounded-lg border border-blue-200 px-3 py-1.5 text-blue-700 transition-colors hover:bg-blue-50 focus-visible:ring-2 focus-visible:ring-blue-500 focus-visible:outline-none dark:border-blue-900 dark:text-blue-300 dark:hover:bg-blue-950/50"
                                :aria-label="`Editar a ${c.nombre}`"
                                @click.stop="editar(c)"
                                @keydown.enter.stop
                            >
                                Editar
                            </button>
                            <button
                                v-if="permisos.eliminar"
                                type="button"
                                class="rounded-lg border border-rose-200 px-3 py-1.5 text-rose-700 transition-colors hover:bg-rose-50 focus-visible:ring-2 focus-visible:ring-rose-500 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-40 dark:border-rose-900 dark:text-rose-300 dark:hover:bg-rose-950/50"
                                :disabled="c.total_envios > 0"
                                :title="
                                    c.total_envios > 0
                                        ? 'Tiene envíos asociados: no se puede eliminar'
                                        : undefined
                                "
                                :aria-label="`Eliminar a ${c.nombre}`"
                                @click.stop="pedirEliminar(c)"
                                @keydown.enter.stop
                            >
                                Eliminar
                            </button>
                        </div>
                    </td>
                </BiTableRow>
            </BiTable>

            <BiPagination
                v-model="page"
                :total="Math.max(1, clientes.last_page)"
                :count="clientes.total"
                unit="clientes"
            />
        </section>

        <!-- Análisis: quiénes concentran la operación (Pareto). -->
        <section
            class="rounded-xl border border-sidebar-border/70 bg-card p-4 shadow-sm sm:p-5"
            aria-label="Top clientes"
        >
            <BiPanelHead
                title="Top clientes por envíos"
                :description="
                    concentracion !== null
                        ? `Los ${analisis.top.length} principales concentran el ${concentracion.toFixed(0)}% de los envíos.`
                        : 'Clientes con mayor volumen de envíos.'
                "
            />

            <BiBarList
                :rows="filasTop"
                :active="null"
                empty="Aún no hay envíos para analizar."
                @select="abrirTop"
            />

            <p
                v-if="lecturaTop"
                class="mt-4 rounded-lg bg-muted px-4 py-3 text-sm leading-relaxed text-muted-foreground"
            >
                {{ lecturaTop }}
            </p>
        </section>

        <ConfirmDialog
            v-model="dialogo"
            title="¿Eliminar cliente?"
            :description="
                aEliminar
                    ? `Se eliminará a «${aEliminar.nombre}». Esta acción no se puede deshacer.`
                    : undefined
            "
            :processing="eliminando"
            :error="errorEliminar"
            @confirmar="confirmarEliminar"
        />
    </div>
</template>
