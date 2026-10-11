<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { colors } from '@/UI/Colors';
import { ChevronLeft, CheckCircle2, AlertTriangle, Truck } from '@/UI/Icons';
import { dashboard } from '@/routes';
import { prioridadInfo, type Prioridad } from '@/config/Envios/envios';
import {
    clientesDemo,
    sucursalesDemo,
    territoriosDemo,
    horasCompromiso,
    PESO_CARGA_PESADA,
    type ClienteOpcion,
    type Opcion,
} from '@/config/Envios/envioForm';

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Envíos', href: '/envios' },
            { title: 'Nuevo envío', href: '#' },
        ],
    },
});

// Utiliza datos de demostración cuando Laravel no proporciona opciones.
const props = withDefaults(
    defineProps<{
        clientes?: ClienteOpcion[];
        sucursales?: Opcion[];
        territorios?: Opcion[];
    }>(),
    {
        clientes: () => clientesDemo,
        sucursales: () => sucursalesDemo,
        territorios: () => territoriosDemo,
    },
);

const H = 3_600_000;
const ahora = ref(Date.now());
let reloj: ReturnType<typeof setInterval> | undefined;

// Define los campos que se envían al controlador de Laravel.
const form = useForm({
    cliente_remitente_id: null as number | null,
    cliente_destinatario_id: null as number | null,
    sucursal_origen_id: null as number | null,
    territorio_destino_id: null as number | null,
    direccion_destino: '',
    peso_kg: '' as number | '',
    volumen_m3: '' as number | '',
    prioridad: 'NORMAL' as Prioridad,
    fecha_comprometida: '',
});

// Formatea fechas para el campo datetime-local, usando la hora local.
const pad = (n: number) => String(n).padStart(2, '0');

const aInputLocal = (fecha: Date) =>
    `${fecha.getFullYear()}-${pad(fecha.getMonth() + 1)}-${pad(fecha.getDate())}T${pad(fecha.getHours())}:${pad(fecha.getMinutes())}`;

// Calcula la fecha sugerida y la fecha mínima según la hora actual.
const sugerida = computed(() =>
    aInputLocal(new Date(ahora.value + horasCompromiso[form.prioridad] * H)),
);

const minimo = computed(() => aInputLocal(new Date(ahora.value)));
const manual = ref(false);

const usarSugerida = () => {
    manual.value = false;
    form.fecha_comprometida = sugerida.value;
};

onMounted(() => {
    usarSugerida();
    // Actualiza los plazos para evitar mostrar información desactualizada.
    reloj = setInterval(() => {
        ahora.value = Date.now();
    }, 60_000);
});

onUnmounted(() => {
    if (reloj) clearInterval(reloj);
});

// Actualiza la fecha sugerida solo si el usuario no la modificó.
watch(
    () => form.prioridad,
    () => {
        if (!manual.value) usarSugerida();
    },
);

// Agrupa los datos necesarios para el resumen del envío.
const nombreDe = (lista: Opcion[], id: number | null) =>
    lista.find((opcion) => opcion.id === id)?.nombre ?? null;

const resumen = computed(() => {
    const fecha = form.fecha_comprometida
        ? new Date(form.fecha_comprometida).getTime()
        : NaN;

    const horas = Number.isFinite(fecha) ? (fecha - ahora.value) / H : null;

    return {
        remitente: nombreDe(props.clientes, form.cliente_remitente_id),
        destinatario: nombreDe(props.clientes, form.cliente_destinatario_id),
        origen: nombreDe(props.sucursales, form.sucursal_origen_id),
        destino: nombreDe(props.territorios, form.territorio_destino_id),
        horas,
        compromisoTexto:
            horas === null
                ? null
                : new Date(fecha).toLocaleString('es-SV', {
                      weekday: 'short',
                      day: '2-digit',
                      month: 'short',
                      hour: '2-digit',
                      minute: '2-digit',
                  }),
    };
});

const plazoTexto = computed(() => {
    const horas = resumen.value.horas;

    if (horas === null) return '—';
    if (horas <= 0) return 'fecha ya vencida';

    return horas >= 48
        ? `en ${(horas / 24).toFixed(1)} días`
        : `en ${Math.ceil(horas)} horas`;
});

// Comprueba los campos obligatorios antes de habilitar el registro.
const checklist = computed(() => [
    { label: 'Remitente', ok: form.cliente_remitente_id !== null },
    { label: 'Destinatario', ok: form.cliente_destinatario_id !== null },
    { label: 'Sucursal de origen', ok: form.sucursal_origen_id !== null },
    { label: 'Territorio de destino', ok: form.territorio_destino_id !== null },
    {
        label: 'Dirección de entrega',
        ok: form.direccion_destino.trim().length > 0,
    },
    {
        label: 'Peso',
        ok:
            form.peso_kg !== '' &&
            Number.isFinite(Number(form.peso_kg)) &&
            Number(form.peso_kg) > 0,
    },
    {
        label: 'Compromiso de entrega',
        ok: resumen.value.horas !== null && resumen.value.horas > 0,
    },
]);

const completados = computed(
    () => checklist.value.filter((campo) => campo.ok).length,
);

const completo = computed(() => completados.value === checklist.value.length);

// Advierte sobre combinaciones que requieren revisión.
const avisos = computed(() => {
    const lista: string[] = [];
    const horas = resumen.value.horas;
    const peso = Number(form.peso_kg);

    if (
        form.cliente_remitente_id !== null &&
        form.cliente_remitente_id === form.cliente_destinatario_id
    ) {
        lista.push('El remitente y el destinatario son el mismo cliente.');
    }

    if (form.peso_kg !== '' && peso >= PESO_CARGA_PESADA) {
        lista.push(
            `Carga pesada (${PESO_CARGA_PESADA} kg o más): verifica la capacidad del vehículo.`,
        );
    }

    if (form.prioridad === 'URGENTE' && horas !== null && horas > 48) {
        lista.push(
            'La prioridad urgente tiene un compromiso superior a 48 horas.',
        );
    }

    if (
        form.prioridad === 'BAJA' &&
        horas !== null &&
        horas > 0 &&
        horas < 48
    ) {
        lista.push('La prioridad baja tiene un plazo inferior a 48 horas.');
    }

    return lista;
});

// Envía los datos y conserva la posición de la página ante errores.
const enviar = () => {
    if (!completo.value || form.processing) return;
    form.post('/envios', {
        preserveScroll: true,
    });
};

// Centraliza los estilos reutilizados en el formulario.
const soft = (hex: string, alpha = '1A') => `${hex}${alpha}`;
const inputClase =
    'h-10 w-full rounded-lg border border-sidebar-border/70 bg-card px-3 text-sm text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none';
const labelClase = 'mb-1.5 block text-xs font-medium text-muted-foreground';
const prioridades = Object.keys(prioridadInfo) as Prioridad[];
// Separa los clientes por tipo para llenar los selectores.
const empresas = computed(() =>
    props.clientes.filter((cliente) => cliente.tipo_cliente === 'EMPRESA'),
);
const personas = computed(() =>
    props.clientes.filter((cliente) => cliente.tipo_cliente === 'PERSONA'),
);
</script>

<template>
    <Head title="Nuevo envío" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
        <!-- Encabezado y navegación -->
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h1 class="text-xl font-semibold text-foreground">
                    Nuevo envío
                </h1>
                <p class="text-sm text-muted-foreground">
                    Registra el envío y define su compromiso de entrega
                </p>
            </div>

            <Link
                href="/envios"
                class="inline-flex items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
            >
                <ChevronLeft class="h-4 w-4" aria-hidden="true" />
                Volver a envíos
            </Link>
        </header>

        <!-- Formulario principal y resumen lateral -->
        <form
            class="grid items-start gap-4 lg:grid-cols-3"
            @submit.prevent="enviar"
        >
            <div class="flex flex-col gap-4 lg:col-span-2">
                <!-- 1. Selección de remitente y destinatario -->
                <section
                    class="rounded-xl border border-sidebar-border/70 bg-card p-5"
                    aria-labelledby="clientes-titulo"
                >
                    <h2
                        id="clientes-titulo"
                        class="mb-4 flex items-center gap-2 text-base font-medium text-foreground"
                    >
                        <span
                            class="flex h-6 w-6 items-center justify-center rounded-full text-xs text-white"
                            :style="{ backgroundColor: colors.azulClaro }"
                        >
                            1
                        </span>
                        Clientes
                    </h2>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="remitente" :class="labelClase">
                                Remitente *
                            </label>
                            <select
                                id="remitente"
                                v-model="form.cliente_remitente_id"
                                required
                                :class="inputClase"
                                :aria-invalid="
                                    !!form.errors.cliente_remitente_id
                                "
                            >
                                <option :value="null" disabled>
                                    Selecciona un cliente
                                </option>
                                <optgroup label="Empresas">
                                    <option
                                        v-for="c in empresas"
                                        :key="c.id"
                                        :value="c.id"
                                    >
                                        {{ c.nombre }}
                                    </option>
                                </optgroup>
                                <optgroup label="Personas">
                                    <option
                                        v-for="c in personas"
                                        :key="c.id"
                                        :value="c.id"
                                    >
                                        {{ c.nombre }}
                                    </option>
                                </optgroup>
                            </select>
                            <p
                                v-if="form.errors.cliente_remitente_id"
                                class="mt-1 text-xs"
                                :style="{ color: colors.rojo }"
                            >
                                {{ form.errors.cliente_remitente_id }}
                            </p>
                        </div>

                        <div>
                            <label for="destinatario" :class="labelClase">
                                Destinatario *
                            </label>
                            <select
                                id="destinatario"
                                v-model="form.cliente_destinatario_id"
                                required
                                :class="inputClase"
                                :aria-invalid="
                                    !!form.errors.cliente_destinatario_id
                                "
                            >
                                <option :value="null" disabled>
                                    Selecciona un cliente
                                </option>
                                <optgroup label="Empresas">
                                    <option
                                        v-for="c in empresas"
                                        :key="c.id"
                                        :value="c.id"
                                    >
                                        {{ c.nombre }}
                                    </option>
                                </optgroup>
                                <optgroup label="Personas">
                                    <option
                                        v-for="c in personas"
                                        :key="c.id"
                                        :value="c.id"
                                    >
                                        {{ c.nombre }}
                                    </option>
                                </optgroup>
                            </select>
                            <p
                                v-if="form.errors.cliente_destinatario_id"
                                class="mt-1 text-xs"
                                :style="{ color: colors.rojo }"
                            >
                                {{ form.errors.cliente_destinatario_id }}
                            </p>
                        </div>
                    </div>
                </section>

                <!-- 2. Información de origen y entrega -->
                <section
                    class="rounded-xl border border-sidebar-border/70 bg-card p-5"
                    aria-labelledby="ruta-titulo"
                >
                    <h2
                        id="ruta-titulo"
                        class="mb-4 flex items-center gap-2 text-base font-medium text-foreground"
                    >
                        <span
                            class="flex h-6 w-6 items-center justify-center rounded-full text-xs text-white"
                            :style="{ backgroundColor: colors.azulClaro }"
                        >
                            2
                        </span>
                        Origen y destino
                    </h2>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="origen" :class="labelClase">
                                Sucursal de origen *
                            </label>
                            <select
                                id="origen"
                                v-model="form.sucursal_origen_id"
                                required
                                :class="inputClase"
                                :aria-invalid="!!form.errors.sucursal_origen_id"
                            >
                                <option :value="null" disabled>
                                    Selecciona una sucursal
                                </option>
                                <option
                                    v-for="s in sucursales"
                                    :key="s.id"
                                    :value="s.id"
                                >
                                    {{ s.nombre }}
                                </option>
                            </select>
                            <p
                                v-if="form.errors.sucursal_origen_id"
                                class="mt-1 text-xs"
                                :style="{ color: colors.rojo }"
                            >
                                {{ form.errors.sucursal_origen_id }}
                            </p>
                        </div>

                        <div>
                            <label for="destino" :class="labelClase">
                                Territorio de destino *
                            </label>
                            <select
                                id="destino"
                                v-model="form.territorio_destino_id"
                                required
                                :class="inputClase"
                                :aria-invalid="
                                    !!form.errors.territorio_destino_id
                                "
                            >
                                <option :value="null" disabled>
                                    Selecciona un territorio
                                </option>
                                <option
                                    v-for="t in territorios"
                                    :key="t.id"
                                    :value="t.id"
                                >
                                    {{ t.nombre }}
                                </option>
                            </select>
                            <p
                                v-if="form.errors.territorio_destino_id"
                                class="mt-1 text-xs"
                                :style="{ color: colors.rojo }"
                            >
                                {{ form.errors.territorio_destino_id }}
                            </p>
                        </div>

                        <div class="sm:col-span-2">
                            <label for="direccion" :class="labelClase">
                                Dirección de entrega *
                            </label>
                            <input
                                id="direccion"
                                v-model="form.direccion_destino"
                                type="text"
                                maxlength="255"
                                required
                                placeholder="Colonia, calle, número, referencia…"
                                :class="inputClase"
                                :aria-invalid="!!form.errors.direccion_destino"
                            />
                            <p
                                v-if="form.errors.direccion_destino"
                                class="mt-1 text-xs"
                                :style="{ color: colors.rojo }"
                            >
                                {{ form.errors.direccion_destino }}
                            </p>
                        </div>
                    </div>
                </section>

                <!-- 3. Características y prioridad del paquete -->
                <section
                    class="rounded-xl border border-sidebar-border/70 bg-card p-5"
                    aria-labelledby="paquete-titulo"
                >
                    <h2
                        id="paquete-titulo"
                        class="mb-4 flex items-center gap-2 text-base font-medium text-foreground"
                    >
                        <span
                            class="flex h-6 w-6 items-center justify-center rounded-full text-xs text-white"
                            :style="{ backgroundColor: colors.azulClaro }"
                        >
                            3
                        </span>
                        Paquete
                    </h2>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="peso" :class="labelClase">
                                Peso (kg) *
                            </label>
                            <input
                                id="peso"
                                v-model.number="form.peso_kg"
                                type="number"
                                min="0.01"
                                step="0.01"
                                required
                                placeholder="0.00"
                                :class="inputClase"
                                :aria-invalid="!!form.errors.peso_kg"
                            />
                            <p
                                v-if="form.errors.peso_kg"
                                class="mt-1 text-xs"
                                :style="{ color: colors.rojo }"
                            >
                                {{ form.errors.peso_kg }}
                            </p>
                        </div>

                        <div>
                            <label for="volumen" :class="labelClase">
                                Volumen (m³)
                                <span class="font-normal">· opcional</span>
                            </label>
                            <input
                                id="volumen"
                                v-model.number="form.volumen_m3"
                                type="number"
                                min="0.01"
                                step="0.01"
                                placeholder="0.00"
                                :class="inputClase"
                                :aria-invalid="!!form.errors.volumen_m3"
                            />
                            <p
                                v-if="form.errors.volumen_m3"
                                class="mt-1 text-xs"
                                :style="{ color: colors.rojo }"
                            >
                                {{ form.errors.volumen_m3 }}
                            </p>
                        </div>

                        <fieldset class="sm:col-span-2">
                            <legend :class="labelClase">Prioridad *</legend>

                            <div class="grid grid-cols-3 gap-2">
                                <button
                                    v-for="key in prioridades"
                                    :key="key"
                                    type="button"
                                    :aria-pressed="form.prioridad === key"
                                    class="rounded-lg border px-3 py-2.5 text-sm font-medium transition-colors"
                                    :class="
                                        form.prioridad === key
                                            ? 'shadow-sm'
                                            : 'border-sidebar-border/70 text-muted-foreground hover:bg-muted'
                                    "
                                    :style="
                                        form.prioridad === key
                                            ? {
                                                  color: prioridadInfo[key]
                                                      .color,
                                                  borderColor:
                                                      prioridadInfo[key].color,
                                                  backgroundColor: soft(
                                                      prioridadInfo[key].color,
                                                  ),
                                              }
                                            : undefined
                                    "
                                    @click="form.prioridad = key"
                                >
                                    {{ prioridadInfo[key].label }}
                                    <span
                                        class="block text-[11px] font-normal opacity-80"
                                    >
                                        {{ horasCompromiso[key] }} h
                                    </span>
                                </button>
                            </div>
                            <p
                                v-if="form.errors.prioridad"
                                class="mt-1 text-xs"
                                :style="{ color: colors.rojo }"
                            >
                                {{ form.errors.prioridad }}
                            </p>
                        </fieldset>
                    </div>
                </section>

                <!-- 4. Configuración de la fecha comprometida -->
                <section
                    class="rounded-xl border border-sidebar-border/70 bg-card p-5"
                    aria-labelledby="compromiso-titulo"
                >
                    <h2
                        id="compromiso-titulo"
                        class="mb-4 flex items-center gap-2 text-base font-medium text-foreground"
                    >
                        <span
                            class="flex h-6 w-6 items-center justify-center rounded-full text-xs text-white"
                            :style="{ backgroundColor: colors.azulClaro }"
                        >
                            4
                        </span>
                        Compromiso de entrega
                    </h2>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label for="compromiso" :class="labelClase">
                                Fecha y hora comprometida *
                            </label>
                            <input
                                id="compromiso"
                                v-model="form.fecha_comprometida"
                                type="datetime-local"
                                :min="minimo"
                                required
                                :class="inputClase"
                                :aria-invalid="!!form.errors.fecha_comprometida"
                                @input="manual = true"
                            />
                            <p
                                v-if="form.errors.fecha_comprometida"
                                class="mt-1 text-xs"
                                :style="{ color: colors.rojo }"
                            >
                                {{ form.errors.fecha_comprometida }}
                            </p>
                        </div>

                        <div class="flex items-end">
                            <p class="text-xs text-muted-foreground">
                                Plazo sugerido según la prioridad ({{
                                    horasCompromiso[form.prioridad]
                                }}
                                h).

                                <button
                                    v-if="manual"
                                    type="button"
                                    class="ml-1 underline"
                                    :style="{ color: colors.azulClaro }"
                                    @click="usarSugerida"
                                >
                                    Restablecer sugerida
                                </button>
                            </p>
                        </div>
                    </div>
                </section>
            </div>

            <!-- Resumen dinámico y avance del formulario -->
            <aside class="lg:sticky lg:top-4">
                <section
                    class="rounded-md p-5 font-mono shadow-sm"
                    :style="{
                        backgroundColor: colors.papel,
                        color: colors.azulOscuro,
                    }"
                    aria-label="Resumen del envío"
                >
                    <div
                        class="flex items-center justify-between border-b-2 border-dashed pb-3"
                        :style="{ borderColor: colors.grisMedio }"
                    >
                        <span
                            class="flex items-center gap-1.5 text-sm font-bold uppercase"
                        >
                            <Truck
                                class="h-4 w-4"
                                :style="{ color: colors.naranjaClaro }"
                                aria-hidden="true"
                            />
                            Guía de envío
                        </span>
                        <span
                            class="text-[10px]"
                            :style="{ color: colors.grisPizarra }"
                        >
                            PK-······
                        </span>
                    </div>

                    <dl class="mt-4 space-y-3 text-xs">
                        <div>
                            <dt :style="{ color: colors.grisPizarra }">
                                REMITENTE → DESTINATARIO
                            </dt>
                            <dd class="mt-0.5 text-sm font-bold">
                                {{ resumen.remitente ?? '—' }} →
                                {{ resumen.destinatario ?? '—' }}
                            </dd>
                        </div>

                        <div>
                            <dt :style="{ color: colors.grisPizarra }">RUTA</dt>
                            <dd class="mt-0.5 text-sm font-bold">
                                {{ resumen.origen ?? '—' }} →
                                {{ resumen.destino ?? '—' }}
                            </dd>
                        </div>

                        <div class="flex justify-between gap-4">
                            <div>
                                <dt :style="{ color: colors.grisPizarra }">
                                    PESO
                                </dt>
                                <dd class="mt-0.5 text-sm font-bold">
                                    {{
                                        form.peso_kg !== '' &&
                                        Number(form.peso_kg) > 0
                                            ? `${Number(form.peso_kg).toFixed(2)} kg`
                                            : '—'
                                    }}
                                </dd>
                            </div>

                            <div>
                                <dt :style="{ color: colors.grisPizarra }">
                                    VOLUMEN
                                </dt>
                                <dd class="mt-0.5 text-sm font-bold">
                                    {{
                                        form.volumen_m3 !== '' &&
                                        Number(form.volumen_m3) > 0
                                            ? `${Number(form.volumen_m3).toFixed(2)} m³`
                                            : '—'
                                    }}
                                </dd>
                            </div>

                            <div>
                                <dt :style="{ color: colors.grisPizarra }">
                                    PRIORIDAD
                                </dt>
                                <dd
                                    class="mt-0.5 text-sm font-bold"
                                    :style="{
                                        color:
                                            form.prioridad === 'URGENTE'
                                                ? colors.naranjaClaro
                                                : undefined,
                                    }"
                                >
                                    {{
                                        prioridadInfo[
                                            form.prioridad
                                        ].label.toUpperCase()
                                    }}
                                </dd>
                            </div>
                        </div>

                        <div
                            class="border-t-2 border-dashed pt-3"
                            :style="{ borderColor: colors.grisMedio }"
                        >
                            <dt :style="{ color: colors.grisPizarra }">
                                ENTREGA COMPROMETIDA
                            </dt>
                            <dd class="mt-0.5 text-sm font-bold">
                                {{ resumen.compromisoTexto ?? '—' }}
                            </dd>
                            <dd :style="{ color: colors.grisPizarra }">
                                {{ plazoTexto }}
                            </dd>
                        </div>
                    </dl>

                    <!-- Indica qué campos obligatorios faltan -->
                    <div class="mt-5">
                        <div
                            class="mb-1.5 flex justify-between text-[11px]"
                            :style="{ color: colors.grisPizarra }"
                        >
                            <span>CAMPOS OBLIGATORIOS</span>
                            <span
                                >{{ completados }}/{{ checklist.length }}</span
                            >
                        </div>

                        <div
                            class="h-1.5 overflow-hidden rounded-full bg-black/10"
                        >
                            <div
                                class="h-full rounded-full transition-all"
                                :style="{
                                    width: `${(completados / checklist.length) * 100}%`,
                                    backgroundColor: completo
                                        ? colors.verde
                                        : colors.azulClaro,
                                }"
                            />
                        </div>

                        <ul class="mt-3 grid grid-cols-1 gap-1 text-[11px]">
                            <li
                                v-for="campo in checklist"
                                :key="campo.label"
                                class="flex items-center gap-1.5"
                                :style="{
                                    color: campo.ok
                                        ? colors.verde
                                        : colors.grisPizarra,
                                }"
                            >
                                <CheckCircle2
                                    v-if="campo.ok"
                                    class="h-3.5 w-3.5"
                                    aria-hidden="true"
                                />
                                <span
                                    v-else
                                    class="h-3.5 w-3.5 rounded-full border"
                                    :style="{ borderColor: colors.grisMedio }"
                                    aria-hidden="true"
                                />
                                {{ campo.label }}
                            </li>
                        </ul>
                    </div>
                </section>

                <!-- Advierte sobre posibles inconsistencias -->
                <ul
                    v-if="avisos.length"
                    class="mt-3 flex flex-col gap-2"
                    aria-label="Avisos del envío"
                >
                    <li
                        v-for="aviso in avisos"
                        :key="aviso"
                        class="flex items-start gap-2 rounded-lg px-3 py-2.5 text-xs text-foreground"
                        :style="{ backgroundColor: soft(colors.amarillo) }"
                    >
                        <AlertTriangle
                            class="mt-0.5 h-4 w-4 shrink-0"
                            :style="{ color: colors.amarillo }"
                            aria-hidden="true"
                        />
                        {{ aviso }}
                    </li>
                </ul>

                <!-- Acciones del formulario -->
                <div class="mt-4 flex gap-2">
                    <button
                        type="submit"
                        class="flex-1 rounded-lg px-4 py-2.5 text-sm font-medium text-white transition-opacity hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40"
                        :style="{ backgroundColor: colors.azulClaro }"
                        :disabled="!completo || form.processing"
                    >
                        {{
                            form.processing ? 'Registrando…' : 'Registrar envío'
                        }}
                    </button>

                    <Link
                        href="/envios"
                        class="rounded-lg border border-sidebar-border/70 bg-card px-4 py-2.5 text-sm font-medium text-foreground hover:bg-muted"
                    >
                        Cancelar
                    </Link>
                </div>
            </aside>
        </form>
    </div>
</template>
