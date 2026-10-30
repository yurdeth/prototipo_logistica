<script setup lang="ts">
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { colors } from '@/UI/Colors';
import {
    CheckCircle2,
    ChevronDown,
    ChevronUp,
    Plus,
    Route,
    Search,
    X,
} from '@/UI/Icons';
import { dashboard } from '@/routes';
import { buscar as buscarEnviosRuta } from '@/routes/rutas/envios';
import { prioridadInfo } from '@/config/Envios/envios';
// ⚠ El import original tenía un espacio al final: 'Despachoform '. Ajusta al nombre real del archivo.
import {
    sucursalesDemo,
    conductoresDemo,
    vehiculosDemo,
    generarEnviosDisponiblesDemo,
    generarOcupacionDemo,
    fechaLocal,
    HORA_SALIDA_DEFECTO,
    INTERVALO_MIN_DEFECTO,
    MARGEN_JUSTO_HORAS,
    type OpcionSimple,
    type Vehiculo,
    type EnvioDisponible,
    type Ocupacion,
} from '@/config/Rutas/Despachoform';
import { H, MIN, fmtHora, fmtNumero, type Aviso } from '@/lib/formularios';
import { useChecklist } from '@/composables/useChecklist';
import FormPage from '@/components/Forms/FormPage.vue';
import FormSection from '@/components/Forms/FormSection.vue';
import StatCard from '@/components/Forms/StatCard.vue';
import SelectField from '@/components/Forms/SelectField.vue';
import InputField from '@/components/Forms/InputField.vue';
import SoftBadge from '@/components/Forms/SoftBadge.vue';
import EmptyState from '@/components/Forms/EmptyState.vue';
import ProgressBar from '@/components/Forms/ProgressBar.vue';
import SummaryPanel from '@/components/Forms/SummaryPanel.vue';
import SummaryGroup from '@/components/Forms/SummaryGroup.vue';
import SummaryItem from '@/components/Forms/SummaryItem.vue';
import ChecklistProgress from '@/components/Forms/ChecklistProgress.vue';
import ObservacionesPanel from '@/components/Forms/ObservacionesPanel.vue';
import FormActions from '@/components/Forms/FormActions.vue';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Rutas', href: '/rutas' },
            { title: 'Nuevo despacho', href: '#' },
        ],
    },
});

const props = withDefaults(
    defineProps<{
        sucursales?: OpcionSimple[];
        conductores?: OpcionSimple[];
        vehiculos?: Vehiculo[];
        envios?: EnvioDisponible[];
        ocupados?: Ocupacion[];
    }>(),
    {
        sucursales: () => sucursalesDemo,
        conductores: () => conductoresDemo,
        vehiculos: () => vehiculosDemo,
        envios: () => generarEnviosDisponiblesDemo(),
        ocupados: () => generarOcupacionDemo(),
    },
);

/* ───────── Formulario ───────── */
const form = useForm({
    sucursal_origen_id: null as number | null,
    conductor_empleado_id: null as number | null,
    vehiculo_id: null as number | null,
    fecha: fechaLocal(new Date()),
    km_planificados: '' as number | '',
    costo_estimado: '' as number | '',
});

const hoy = fechaLocal(new Date());
const horaSalida = ref(HORA_SALIDA_DEFECTO);
const intervalo = ref(INTERVALO_MIN_DEFECTO);

const etiquetaVehiculo = (v: Vehiculo) =>
    `${v.placa} · ${v.tipo}${v.capacidad_kg ? ` (${v.capacidad_kg} kg)` : ''}`;

/* ───────── Paradas ───────── */
const paradas = ref<EnvioDisponible[]>([]);
const busqueda = ref('');

const porCompromiso = (a: EnvioDisponible, b: EnvioDisponible) =>
    Date.parse(a.fecha_comprometida) - Date.parse(b.fecha_comprometida);

const idsSeleccionados = computed(
    () => new Set(paradas.value.map((p) => p.id_envio)),
);

/* Listado de disponibles paginado en el servidor con scroll infinito: la
   primera página llega renderizada por Laravel (props.envios) y el resto se
   pide al endpoint de búsqueda al acercarse al final del scroll. El front
   solo excluye las paradas ya seleccionadas. */
const TAM_PAGINA = 20;

const items = ref<EnvioDisponible[]>([]);
const cargando = ref(false);
const errorBusqueda = ref<string | null>(null);
const hayMas = ref(false);
const paginaSiguiente = ref(1);
let controlador: AbortController | null = null;
let temporizador: ReturnType<typeof setTimeout> | undefined;

if (props.envios.length > 0) {
    items.value = [...props.envios];
    paginaSiguiente.value = 2;
    hayMas.value = props.envios.length >= TAM_PAGINA;
}

const cargar = async (reiniciar: boolean) => {
    if (cargando.value) return;
    if (!reiniciar && !hayMas.value) return;

    controlador?.abort();
    controlador = new AbortController();
    cargando.value = true;
    errorBusqueda.value = null;

    const pagina = reiniciar ? 1 : paginaSiguiente.value;

    // El filtro de sucursal viaja en TODAS las páginas: cada llamada del
    // scroll infinito pide solo envíos de la sucursal de origen del despacho.
    const filtroSucursal =
        form.sucursal_origen_id !== null
            ? { sucursal_id: form.sucursal_origen_id }
            : {};

    try {
        const respuesta = await fetch(
            buscarEnviosRuta.url({
                query: {
                    q: busqueda.value.trim(),
                    page: pagina,
                    ...filtroSucursal,
                },
            }),
            {
                signal: controlador.signal,
                headers: { Accept: 'application/json' },
            },
        );

        if (!respuesta.ok) {
            throw new Error(`La búsqueda falló (${respuesta.status}).`);
        }

        const cuerpo = (await respuesta.json()) as {
            data: EnvioDisponible[];
            hay_mas: boolean;
        };

        items.value = reiniciar
            ? cuerpo.data
            : [...items.value, ...cuerpo.data];
        hayMas.value = cuerpo.hay_mas;
        paginaSiguiente.value = pagina + 1;
    } catch (err) {
        if (controlador.signal.aborted) return;
        errorBusqueda.value =
            err instanceof Error
                ? err.message
                : 'No se pudo completar la búsqueda.';
    } finally {
        if (!controlador.signal.aborted) cargando.value = false;
    }
};

/* Sin término de búsqueda y sin sucursal elegida, vuelve a la página precargada;
   con sucursal elegida, pide al servidor la lista ya filtrada. */
const reiniciarListado = () => {
    if (form.sucursal_origen_id !== null) {
        void cargar(true);

        return;
    }

    controlador?.abort();
    cargando.value = false;
    errorBusqueda.value = null;
    items.value = [...props.envios];
    paginaSiguiente.value = 2;
    hayMas.value = props.envios.length >= TAM_PAGINA;
};

watch(busqueda, (valor) => {
    clearTimeout(temporizador);

    temporizador = setTimeout(() => {
        if (valor.trim()) {
            void cargar(true);

            return;
        }

        reiniciarListado();
    }, 300);
});

watch(
    () => form.sucursal_origen_id,
    () => {
        clearTimeout(temporizador);
        reiniciarListado();
    },
);

onBeforeUnmount(() => {
    clearTimeout(temporizador);
    controlador?.abort();
});

// Pide la página siguiente cuando el scroll se acerca al final de la lista
const alScroll = (e: Event) => {
    const el = e.target as HTMLElement;

    if (!hayMas.value || cargando.value) return;

    if (el.scrollTop + el.clientHeight >= el.scrollHeight - 60) {
        void cargar(false);
    }
};

const disponibles = computed(() =>
    items.value
        .filter((e) => !idsSeleccionados.value.has(e.id_envio))
        .sort(porCompromiso),
);

const agregar = (e: EnvioDisponible) => paradas.value.push(e);
const quitar = (i: number) => paradas.value.splice(i, 1);
const ordenarPorCompromiso = () => paradas.value.sort(porCompromiso);

const mover = (i: number, dir: -1 | 1) => {
    const j = i + dir;
    if (j < 0 || j >= paradas.value.length) return;
    [paradas.value[i], paradas.value[j]] = [paradas.value[j], paradas.value[i]];
};

/* ───────── Plan de ruta: hora estimada + margen, calculado UNA sola vez ───────── */
type Margen = 'a_tiempo' | 'justo' | 'tarde';

const margenInfo: Record<Margen, { label: string; color: string }> = {
    a_tiempo: { label: 'A tiempo', color: colors.verde },
    justo: { label: 'Plazo ajustado', color: colors.amarillo },
    tarde: { label: 'Fuera de plazo', color: colors.rojo },
};

const calcularMargen = (e: EnvioDisponible, hora: number): Margen => {
    const horas = (Date.parse(e.fecha_comprometida) - hora) / H;

    if (Number.isNaN(horas)) return 'a_tiempo';
    if (horas < 0) return 'tarde';

    return horas < MARGEN_JUSTO_HORAS ? 'justo' : 'a_tiempo';
};

const inicioMs = computed(() =>
    new Date(`${form.fecha}T${horaSalida.value}`).getTime(),
);

const plan = computed(() =>
    paradas.value.map((envio, i) => {
        const hora = inicioMs.value + (i + 1) * intervalo.value * MIN;
        return { envio, hora, margen: calcularMargen(envio, hora) };
    }),
);

const nTarde = computed(
    () => plan.value.filter((p) => p.margen === 'tarde').length,
);
const nUrgentes = computed(
    () => paradas.value.filter((e) => e.prioridad === 'URGENTE').length,
);
const horaFin = computed(() => plan.value.at(-1)?.hora ?? null);

const venceEn = (iso: string) => {
    const h = (Date.parse(iso) - Date.now()) / H;

    if (h < 1) return '<1 h';
    if (h >= 48) return `${(h / 24).toFixed(0)} días`;

    return `${h.toFixed(0)} h`;
};

/* ───────── Vehículo y carga ───────── */
const vehiculo = computed(
    () => props.vehiculos.find((v) => v.id === form.vehiculo_id) ?? null,
);
const pesoTotal = computed(() =>
    paradas.value.reduce((a, e) => a + e.peso_kg, 0),
);

const cargaPct = computed(() =>
    vehiculo.value?.capacidad_kg
        ? (pesoTotal.value / vehiculo.value.capacidad_kg) * 100
        : null,
);

const cargaColor = computed(() =>
    cargaPct.value === null
        ? colors.verde
        : cargaPct.value > 100
          ? colors.rojo
          : cargaPct.value > 85
            ? colors.amarillo
            : colors.verde,
);

// Un envío solo se agrega si el vehículo elegido aún puede cargarlo
const cabeEnVehiculo = (e: EnvioDisponible) =>
    !!vehiculo.value?.capacidad_kg &&
    pesoTotal.value + e.peso_kg <= vehiculo.value.capacidad_kg;

// El despacho solo levanta envíos registrados en su sucursal de origen
const deOtraSucursal = (e: EnvioDisponible) =>
    form.sucursal_origen_id !== null &&
    e.sucursal_origen_id !== form.sucursal_origen_id;

const capacidadLibre = computed(() =>
    vehiculo.value?.capacidad_kg
        ? Math.max(0, vehiculo.value.capacidad_kg - pesoTotal.value)
        : null,
);

/* ───────── Costo ───────── */
const costoManual = ref(false);

const costoSugerido = computed(() =>
    vehiculo.value?.costo_km && Number(form.km_planificados) > 0
        ? Math.round(
              Number(form.km_planificados) * vehiculo.value.costo_km * 100,
          ) / 100
        : null,
);

const usarCostoSugerido = () => {
    costoManual.value = false;
    form.costo_estimado = costoSugerido.value ?? '';
};

watch(costoSugerido, () => {
    if (!costoManual.value) form.costo_estimado = costoSugerido.value ?? '';
});

const costoTexto = computed(() =>
    form.costo_estimado !== ''
        ? `$${Number(form.costo_estimado).toFixed(2)}`
        : '—',
);

/* ───────── Resumen / validación ───────── */
const nombreDe = (lista: OpcionSimple[], id: number | null) =>
    lista.find((o) => o.id === id)?.nombre ?? null;

const origen = computed(() =>
    nombreDe(props.sucursales, form.sucursal_origen_id),
);
const conductor = computed(() =>
    nombreDe(props.conductores, form.conductor_empleado_id),
);

const checklist = computed(() => [
    { label: 'Sucursal de origen', ok: form.sucursal_origen_id !== null },
    { label: 'Fecha programada', ok: !!form.fecha },
    { label: 'Conductor asignado', ok: form.conductor_empleado_id !== null },
    { label: 'Vehículo asignado', ok: form.vehiculo_id !== null },
    { label: 'Al menos una parada', ok: paradas.value.length > 0 },
]);

const { completados, total, completo } = useChecklist(checklist);

const estaOcupado = (
    campo: 'conductor_id' | 'vehiculo_id',
    valor: number | null,
) =>
    valor !== null &&
    props.ocupados.some((o) => o.fecha === form.fecha && o[campo] === valor);

/* La reserva es por día: el recurso ocupado para la fecha elegida no se
   puede seleccionar; para otra fecha vuelve a estar disponible. */
const motivoOcupacion = (
    campo: 'conductor_id' | 'vehiculo_id',
    id: number,
): string | null => {
    const ocupacion = props.ocupados.find(
        (o) => o.fecha === form.fecha && o[campo] === id,
    );

    return ocupacion ? `Ocupado el ${form.fecha}` : null;
};

const motivoConductor = (c: OpcionSimple) =>
    motivoOcupacion('conductor_id', c.id);
const motivoVehiculo = (v: Vehiculo) => motivoOcupacion('vehiculo_id', v.id);

// Si la fecha cambia a un día donde el recurso elegido está ocupado, se libera
watch(
    () => form.fecha,
    () => {
        if (estaOcupado('conductor_id', form.conductor_empleado_id)) {
            form.conductor_empleado_id = null;
        }

        if (estaOcupado('vehiculo_id', form.vehiculo_id)) {
            form.vehiculo_id = null;
        }
    },
);

const avisos = computed<Aviso[]>(() => {
    const lista: Aviso[] = [];

    if (estaOcupado('conductor_id', form.conductor_empleado_id)) {
        lista.push({
            texto: 'El conductor ya tiene un despacho asignado para esta fecha.',
            grave: true,
        });
    }

    if (estaOcupado('vehiculo_id', form.vehiculo_id)) {
        lista.push({
            texto: 'El vehículo ya tiene un despacho asignado para esta fecha.',
            grave: true,
        });
    }

    if (cargaPct.value !== null && cargaPct.value > 100) {
        lista.push({
            texto: `La carga de ${pesoTotal.value.toFixed(1)} kg supera la capacidad del vehículo.`,
            grave: true,
        });
    } else if (cargaPct.value !== null && cargaPct.value > 85) {
        lista.push({
            texto: 'La carga supera el 85 % de la capacidad del vehículo.',
            grave: false,
        });
    }

    if (nTarde.value) {
        lista.push({
            texto: `${nTarde.value} parada(s) tienen una hora estimada posterior al compromiso de entrega.`,
            grave: true,
        });
    }

    if (paradas.value.length && !Number(form.km_planificados)) {
        lista.push({
            texto: 'Registra los kilómetros planificados para estimar el costo del despacho.',
            grave: false,
        });
    }

    return lista;
});

const hayGraves = computed(() => avisos.value.some((a) => a.grave));

const errorParadas = computed(
    () => Reflect.get(form.errors, 'paradas') as string | undefined,
);

/* ───────── Envío ───────── */
const enviar = () => {
    if (!completo.value || form.processing) return;

    form.transform((data) => ({
        ...data,
        paradas: plan.value.map(({ envio, hora }, i) => ({
            envio_id: envio.id_envio,
            orden: i + 1,
            hora_estimada: Number.isNaN(hora)
                ? null
                : new Date(hora).toISOString(),
        })),
    })).post('/rutas', { preserveScroll: true });
};

const btnIcono =
    'rounded p-1.5 text-slate-500 transition hover:bg-slate-100 hover:text-slate-900 disabled:cursor-not-allowed disabled:opacity-30';
</script>

<template>
    <Head title="Nuevo despacho" />

    <FormPage
        title="Crear despacho"
        description="Configura los recursos, programa la salida y organiza las entregas."
        :crumbs="[
            { label: 'Gestión logística', href: '/rutas' },
            { label: 'Despachos' },
        ]"
        back-href="/rutas"
        back-label="Volver a rutas"
    >
        <!-- Resumen superior -->
        <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <StatCard
                label="Paradas programadas"
                :value="paradas.length"
                hint="Entregas seleccionadas"
            />
            <StatCard
                label="Peso total"
                :value="fmtNumero(pesoTotal)"
                unit="kg"
                hint="Carga de la ruta"
            />
            <StatCard
                label="Costo estimado"
                :value="costoTexto"
                hint="Valor de planificación"
            />
            <StatCard
                label="Estado de preparación"
                :value="`${completados}/${total}`"
                :dot="completo ? 'ok' : 'pending'"
                hint="Requisitos completados"
            />
        </section>

        <form
            class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_350px]"
            @submit.prevent="enviar"
        >
            <div class="min-w-0 space-y-6">
                <!-- 1. Datos del despacho -->
                <FormSection
                    title="Datos del despacho"
                    description="Define el origen, la fecha y los recursos asignados."
                >
                    <template #icon><Route class="h-5 w-5" /></template>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <SelectField
                            id="origen"
                            v-model="form.sucursal_origen_id"
                            label="Sucursal de origen"
                            placeholder="Seleccionar sucursal"
                            :options="sucursales"
                            required
                            :error="form.errors.sucursal_origen_id"
                        />
                        <InputField
                            id="fecha"
                            v-model="form.fecha"
                            label="Fecha programada"
                            type="date"
                            :min="hoy"
                            required
                            :error="form.errors.fecha"
                        />
                        <SelectField
                            id="conductor"
                            v-model="form.conductor_empleado_id"
                            label="Conductor responsable"
                            placeholder="Seleccionar conductor"
                            :options="conductores"
                            :option-disabled="motivoConductor"
                            required
                            :error="form.errors.conductor_empleado_id"
                        />
                        <SelectField
                            id="vehiculo"
                            v-model="form.vehiculo_id"
                            label="Vehículo asignado"
                            placeholder="Seleccionar vehículo"
                            :options="vehiculos"
                            :option-label="etiquetaVehiculo"
                            :option-disabled="motivoVehiculo"
                            required
                            :error="form.errors.vehiculo_id"
                        />
                    </div>
                </FormSection>

                <!-- 2. Planificación -->
                <FormSection
                    title="Planificación operativa"
                    description="Establece los parámetros de salida, distancia y costo."
                >
                    <template #icon><CheckCircle2 class="h-5 w-5" /></template>

                    <div class="grid gap-5 sm:grid-cols-2">
                        <InputField
                            id="km"
                            v-model="form.km_planificados"
                            label="Kilómetros planificados"
                            type="number"
                            min="0.01"
                            step="0.01"
                            placeholder="0.00"
                            suffix="km"
                            :error="form.errors.km_planificados"
                        />
                        <InputField
                            id="costo"
                            v-model="form.costo_estimado"
                            label="Costo estimado"
                            type="number"
                            min="0"
                            step="0.01"
                            placeholder="0.00"
                            prefix="$"
                            :error="form.errors.costo_estimado"
                            @input="costoManual = true"
                        >
                            <template #hint>
                                <template v-if="costoSugerido !== null"
                                    >Sugerido: ${{
                                        costoSugerido.toFixed(2)
                                    }}</template
                                >
                                <template v-else
                                    >Se calculará según el vehículo y la
                                    distancia.</template
                                >
                                <button
                                    v-if="costoManual && costoSugerido !== null"
                                    type="button"
                                    class="ml-1 font-medium text-blue-700 hover:underline"
                                    @click="usarCostoSugerido"
                                >
                                    Aplicar sugerido
                                </button>
                            </template>
                        </InputField>
                        <InputField
                            id="salida"
                            v-model="horaSalida"
                            label="Hora de salida"
                            type="time"
                        />
                        <InputField
                            id="intervalo"
                            v-model="intervalo"
                            label="Intervalo entre paradas"
                            type="number"
                            min="5"
                            step="5"
                            suffix="min"
                        />
                    </div>

                    <template #footer>
                        <p class="text-xs leading-relaxed text-slate-500">
                            Las horas estimadas se calculan utilizando la hora
                            de salida y el intervalo definido. Son valores de
                            planificación, no registros de llegada reales.
                        </p>
                    </template>
                </FormSection>

                <!-- 3. Gestión de paradas -->
                <FormSection
                    title="Gestión de paradas"
                    description="Selecciona los envíos y establece su orden de atención."
                    flush
                >
                    <template #icon><Route class="h-5 w-5" /></template>
                    <template #actions>
                        <span
                            class="inline-flex w-fit items-center rounded-md bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-700"
                        >
                            {{ paradas.length }} seleccionadas
                        </span>
                    </template>

                    <div class="grid xl:grid-cols-2">
                        <!-- Disponibles -->
                        <div
                            class="min-w-0 border-b border-slate-200 p-5 sm:p-6 xl:border-r xl:border-b-0"
                        >
                            <div class="mb-3 flex items-center justify-between">
                                <h3
                                    class="text-sm font-semibold text-slate-800"
                                >
                                    Envíos disponibles
                                </h3>
                                <span class="text-xs text-slate-500">
                                    {{
                                        cargando
                                            ? 'Buscando...'
                                            : (errorBusqueda ??
                                              `${disponibles.length} en la lista`)
                                    }}
                                </span>
                            </div>

                            <p
                                v-if="!vehiculo"
                                class="mb-3 rounded-md bg-amber-50 px-3 py-2 text-xs text-amber-800"
                            >
                                Selecciona un vehículo para habilitar la carga
                                de envíos.
                            </p>
                            <p
                                v-else
                                class="mb-3 rounded-md bg-slate-50 px-3 py-2 text-xs text-slate-600"
                            >
                                Capacidad libre del vehículo:
                                <span
                                    class="font-semibold"
                                    :class="
                                        capacidadLibre === 0
                                            ? 'text-red-600'
                                            : 'text-slate-800'
                                    "
                                    >{{ capacidadLibre?.toFixed(1) }} kg</span
                                >
                            </p>

                            <div class="relative mb-4">
                                <Search
                                    class="absolute top-2.5 left-3 h-4 w-4 text-slate-400"
                                />
                                <input
                                    v-model="busqueda"
                                    type="search"
                                    placeholder="Buscar por guía o destino..."
                                    aria-label="Buscar envíos disponibles"
                                    class="h-9 w-full rounded-md border border-slate-300 bg-white pr-3 pl-9 text-sm text-slate-800 transition outline-none placeholder:text-slate-400 focus:border-blue-600 focus:ring-2 focus:ring-blue-600/15"
                                />
                            </div>

                            <ul
                                class="flex max-h-[420px] flex-col gap-2 overflow-y-auto pr-1"
                                @scroll="alScroll"
                            >
                                <li
                                    v-for="e in disponibles"
                                    :key="e.id_envio"
                                    class="flex items-center gap-3 rounded-md border border-slate-200 p-3 transition hover:border-slate-300 hover:bg-slate-50"
                                >
                                    <div class="min-w-0 flex-1">
                                        <div
                                            class="flex flex-wrap items-center gap-2"
                                        >
                                            <span
                                                class="font-mono text-xs font-semibold text-slate-800"
                                                >{{ e.codigo_guia }}</span
                                            >
                                            <SoftBadge
                                                :label="
                                                    prioridadInfo[e.prioridad]
                                                        .label
                                                "
                                                :color="
                                                    prioridadInfo[e.prioridad]
                                                        .color
                                                "
                                            />
                                        </div>
                                        <p
                                            class="mt-1 truncate text-sm text-slate-700"
                                        >
                                            {{ e.destino }}
                                        </p>
                                        <p class="mt-1 text-xs text-slate-500">
                                            {{ e.peso_kg.toFixed(1) }} kg
                                            <span class="mx-1 text-slate-300"
                                                >·</span
                                            >
                                            Compromiso en
                                            {{ venceEn(e.fecha_comprometida) }}
                                        </p>
                                    </div>

                                    <button
                                        type="button"
                                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-md border border-slate-300 bg-white text-slate-600 transition hover:border-blue-600 hover:bg-blue-50 hover:text-blue-700 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:border-slate-300 disabled:hover:bg-white disabled:hover:text-slate-600"
                                        :disabled="
                                            !vehiculo ||
                                            !cabeEnVehiculo(e) ||
                                            deOtraSucursal(e)
                                        "
                                        :aria-label="
                                            !vehiculo
                                                ? `Selecciona un vehículo antes de agregar ${e.codigo_guia}`
                                                : `Agregar ${e.codigo_guia}`
                                        "
                                        :title="
                                            !vehiculo
                                                ? 'Selecciona primero un vehículo'
                                                : deOtraSucursal(e)
                                                  ? 'Pertenece a otra sucursal de origen'
                                                  : !cabeEnVehiculo(e)
                                                    ? `Excede la capacidad libre del vehículo (${capacidadLibre?.toFixed(1)} kg disponibles)`
                                                    : undefined
                                        "
                                        @click="agregar(e)"
                                    >
                                        <Plus class="h-4 w-4" />
                                    </button>
                                </li>

                                <EmptyState
                                    v-if="!disponibles.length"
                                    title="No hay envíos disponibles"
                                    :description="
                                        cargando
                                            ? 'Consultando envíos...'
                                            : 'Prueba con otro término de búsqueda.'
                                    "
                                >
                                    <template #icon
                                        ><Search class="h-6 w-6"
                                    /></template>
                                </EmptyState>

                                <li
                                    v-if="cargando && disponibles.length"
                                    class="py-2 text-center text-xs text-slate-500"
                                >
                                    Cargando más envíos...
                                </li>
                                <li
                                    v-else-if="
                                        !hayMas &&
                                        !errorBusqueda &&
                                        disponibles.length
                                    "
                                    class="py-2 text-center text-xs text-slate-400"
                                >
                                    No hay más envíos
                                </li>
                            </ul>
                        </div>

                        <!-- Seleccionadas -->
                        <div class="min-w-0 p-5 sm:p-6">
                            <div
                                class="mb-3 flex flex-wrap items-center justify-between gap-2"
                            >
                                <h3
                                    class="text-sm font-semibold text-slate-800"
                                >
                                    Orden de entrega
                                </h3>
                                <button
                                    v-if="paradas.length > 1"
                                    type="button"
                                    class="text-xs font-medium text-blue-700 hover:text-blue-900 hover:underline"
                                    @click="ordenarPorCompromiso"
                                >
                                    Ordenar por compromiso
                                </button>
                            </div>

                            <ol
                                class="flex max-h-[420px] flex-col gap-2 overflow-y-auto pr-1"
                            >
                                <li
                                    v-for="(p, i) in plan"
                                    :key="p.envio.id_envio"
                                    class="flex items-start gap-3 rounded-md border p-3"
                                    :class="
                                        p.margen === 'tarde'
                                            ? 'border-red-200 bg-red-50/50'
                                            : 'border-slate-200 bg-white'
                                    "
                                >
                                    <span
                                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-slate-100 text-xs font-semibold text-slate-700"
                                    >
                                        {{ String(i + 1).padStart(2, '0') }}
                                    </span>

                                    <div class="min-w-0 flex-1">
                                        <div
                                            class="flex flex-wrap items-center gap-2"
                                        >
                                            <span
                                                class="font-mono text-xs font-semibold text-slate-800"
                                                >{{ p.envio.codigo_guia }}</span
                                            >
                                            <SoftBadge
                                                :label="
                                                    margenInfo[p.margen].label
                                                "
                                                :color="
                                                    margenInfo[p.margen].color
                                                "
                                            />
                                        </div>

                                        <p
                                            class="mt-1 truncate text-sm text-slate-700"
                                        >
                                            {{ p.envio.destino }}
                                        </p>
                                        <p class="mt-1 text-xs text-slate-500">
                                            Llegada estimada:
                                            <span
                                                class="font-medium text-slate-700"
                                                >{{ fmtHora(p.hora) }}</span
                                            >
                                        </p>

                                        <div
                                            class="mt-2 flex items-center gap-1"
                                        >
                                            <button
                                                type="button"
                                                :class="btnIcono"
                                                :disabled="i === 0"
                                                aria-label="Subir parada"
                                                @click="mover(i, -1)"
                                            >
                                                <ChevronUp class="h-4 w-4" />
                                            </button>
                                            <button
                                                type="button"
                                                :class="btnIcono"
                                                :disabled="
                                                    i === plan.length - 1
                                                "
                                                aria-label="Bajar parada"
                                                @click="mover(i, 1)"
                                            >
                                                <ChevronDown class="h-4 w-4" />
                                            </button>
                                            <button
                                                type="button"
                                                class="ml-auto inline-flex items-center gap-1 rounded px-2 py-1.5 text-xs font-medium text-slate-500 transition hover:bg-red-50 hover:text-red-700"
                                                :aria-label="`Quitar ${p.envio.codigo_guia}`"
                                                @click="quitar(i)"
                                            >
                                                <X class="h-3.5 w-3.5" />
                                                Quitar
                                            </button>
                                        </div>
                                    </div>
                                </li>

                                <EmptyState
                                    v-if="!plan.length"
                                    title="Sin paradas asignadas"
                                    description="Agrega envíos desde la lista disponible para construir el itinerario."
                                >
                                    <template #icon
                                        ><Route class="h-7 w-7"
                                    /></template>
                                </EmptyState>
                            </ol>

                            <p
                                v-if="errorParadas"
                                class="mt-1 text-xs text-red-600"
                            >
                                {{ errorParadas }}
                            </p>
                        </div>
                    </div>
                </FormSection>
            </div>

            <!-- Panel lateral -->
            <aside class="min-w-0 space-y-4 xl:sticky xl:top-6">
                <SummaryPanel
                    title="Resumen del despacho"
                    subtitle="Vista previa de la planificación"
                >
                    <SummaryGroup label="Origen y fecha">
                        <p class="text-sm font-semibold text-slate-900">
                            {{ origen ?? 'Sin asignar' }}
                        </p>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ form.fecha || 'Sin fecha' }}
                        </p>
                    </SummaryGroup>

                    <SummaryGroup label="Recursos asignados">
                        <dl class="space-y-3">
                            <SummaryItem label="Conductor">{{
                                conductor ?? 'Sin asignar'
                            }}</SummaryItem>
                            <SummaryItem label="Vehículo">
                                {{
                                    vehiculo
                                        ? `${vehiculo.placa} · ${vehiculo.tipo}`
                                        : 'Sin asignar'
                                }}
                            </SummaryItem>
                        </dl>
                    </SummaryGroup>

                    <SummaryGroup label="Indicadores de ruta">
                        <dl class="grid grid-cols-2 gap-4">
                            <SummaryItem label="Paradas" size="lg">{{
                                paradas.length
                            }}</SummaryItem>
                            <SummaryItem
                                label="Urgentes"
                                size="lg"
                                :value-class="
                                    nUrgentes ? 'text-red-600' : undefined
                                "
                            >
                                {{ nUrgentes }}
                            </SummaryItem>
                            <SummaryItem label="Hora estimada de fin">{{
                                horaFin ? fmtHora(horaFin) : '—'
                            }}</SummaryItem>
                            <SummaryItem label="Distancia">
                                {{
                                    Number(form.km_planificados) > 0
                                        ? `${Number(form.km_planificados).toFixed(1)} km`
                                        : '—'
                                }}
                            </SummaryItem>
                        </dl>
                    </SummaryGroup>

                    <SummaryGroup label="Capacidad de carga">
                        <template #side>
                            <span class="text-xs font-semibold text-slate-700">
                                {{
                                    cargaPct !== null
                                        ? `${cargaPct.toFixed(0)}%`
                                        : '—'
                                }}
                            </span>
                        </template>

                        <p class="text-sm text-slate-700">
                            {{ pesoTotal.toFixed(1) }} kg
                            <span class="text-slate-400">{{
                                vehiculo?.capacidad_kg
                                    ? `/ ${vehiculo.capacidad_kg} kg`
                                    : ''
                            }}</span>
                        </p>

                        <ProgressBar
                            v-if="cargaPct !== null"
                            class="mt-2"
                            :value="cargaPct"
                            :color="cargaColor"
                        />

                        <p
                            v-if="cargaPct !== null && cargaPct > 100"
                            class="mt-2 text-xs font-medium text-red-600"
                        >
                            La carga excede la capacidad del vehículo.
                        </p>
                        <p
                            v-else-if="cargaPct !== null && cargaPct > 85"
                            class="mt-2 text-xs text-amber-700"
                        >
                            La carga se encuentra cerca del límite permitido.
                        </p>
                    </SummaryGroup>

                    <SummaryGroup
                        ><ChecklistProgress :items="checklist"
                    /></SummaryGroup>
                </SummaryPanel>

                <ObservacionesPanel :avisos="avisos" />

                <FormActions
                    submit-label="Registrar despacho"
                    processing-label="Registrando despacho..."
                    :processing="form.processing"
                    :disabled="!completo"
                    cancel-href="/rutas"
                    :warning="
                        hayGraves
                            ? 'Hay observaciones críticas que conviene resolver antes de registrar el despacho.'
                            : null
                    "
                    hint="Verifica los datos y la secuencia de entrega antes de confirmar."
                >
                    <template #icon><Route class="h-4 w-4" /></template>
                </FormActions>
            </aside>
        </form>
    </FormPage>
</template>
