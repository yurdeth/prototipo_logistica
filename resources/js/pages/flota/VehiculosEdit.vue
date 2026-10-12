<script setup lang="ts">
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { Truck } from '@/UI/Icons';
import { dashboard } from '@/routes';
import {
    estadosVehiculoDemo,
    FORMATO_PLACA,
    marcasVehiculoDemo,
    modelosVehiculoDemo,
    normalizarPlaca,
    sucursalesVehiculoDemo,
    tiposVehiculoDemo,
    type EstadoVehiculo,
    type MarcaVehiculoCatalogo,
    type ModeloVehiculoCatalogo,
    type SucursalVehiculo,
    type TipoVehiculo,
    type Vehiculo,
} from '@/config/Flota/vehiculos';
import { apiJson, type ApiError } from '@/lib/api';
import { esPositivo, fmtNumero, type Aviso } from '@/lib/formularios';
import { useChecklist } from '@/composables/useChecklist';
import FormPage from '@/components/Forms/FormPage.vue';
import FormSection from '@/components/Forms/FormSection.vue';
import StatCard from '@/components/Forms/StatCard.vue';
import SelectField from '@/components/Forms/SelectField.vue';
import InputField from '@/components/Forms/InputField.vue';
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
            { title: 'Vehículos', href: '/vehiculos' },
            { title: 'Editar vehículo', href: '#' },
        ],
    },
});

const pagina = usePage();

// Id del vehículo desde la URL /vehiculos/{id}/editar
const idVehiculo = computed(() => {
    const segmentos = pagina.url.split('/').filter(Boolean);

    return segmentos[0] === 'vehiculos' && segmentos[1]
        ? Number(segmentos[1])
        : null;
});

/* ───────── Catálogos (reales cuando responde la API) ───────── */
const tipos = ref<TipoVehiculo[]>(tiposVehiculoDemo);
const estados = ref<EstadoVehiculo[]>(estadosVehiculoDemo);
const sucursales = ref<SucursalVehiculo[]>(sucursalesVehiculoDemo);
const marcas = ref<MarcaVehiculoCatalogo[]>(marcasVehiculoDemo);
const modelos = ref<ModeloVehiculoCatalogo[]>(modelosVehiculoDemo);

/* ───────── Formulario ───────── */
// Límites del año del vehículo, compartidos por el input y los avisos.
const anioActual = new Date().getFullYear();

const form = useForm({
    placa: '',
    tipo_vehiculo_id: null as number | null,
    estado_vehiculo_id: null as number | null,
    sucursal_id: null as number | null,
    marca_vehiculo_id: null as number | null,
    modelo_vehiculo_id: null as number | null,
    anio: '' as number | '',
    consumo_km_gal: '' as number | '',
});

const cargando = ref(true);

// Mientras el formulario se hidrata con el vehículo cargado, los watchers de
// marca/modelo no deben reaccionar: borrarían los valores recién asignados
// (bug: guardar sin tocar nada enviaba modelo en null y "borraba" marca/modelo).
let hidratando = false;

const cargarDatos = async (): Promise<void> => {
    if (idVehiculo.value === null) return;

    try {
        const [catalogos, respuesta] = await Promise.all([
            apiJson<{
                tipos: TipoVehiculo[];
                estados: EstadoVehiculo[];
                sucursales: SucursalVehiculo[];
                marcas: MarcaVehiculoCatalogo[];
                modelos: ModeloVehiculoCatalogo[];
            }>('/api/vehiculos/opciones'),
            apiJson<{
                vehiculo: Vehiculo & {
                    modelos: {
                        id_modelo: number;
                        pivot: { marca_vehiculo_id: number };
                    }[];
                };
            }>(`/api/vehiculos/${idVehiculo.value}`),
        ]);

        hidratando = true;
        if (catalogos.tipos?.length) tipos.value = catalogos.tipos;
        if (catalogos.estados?.length) estados.value = catalogos.estados;
        if (catalogos.sucursales?.length)
            sucursales.value = catalogos.sucursales;
        if (catalogos.marcas?.length) marcas.value = catalogos.marcas;
        if (catalogos.modelos?.length) modelos.value = catalogos.modelos;

        const v = respuesta.vehiculo;
        form.placa = v.placa;
        form.tipo_vehiculo_id = v.tipo.id_tipo_vehiculo;
        form.estado_vehiculo_id = v.estado.id_estado;
        form.sucursal_id = v.sucursal.id_sucursal;
        form.marca_vehiculo_id = v.modelos[0]?.pivot.marca_vehiculo_id ?? null;
        form.modelo_vehiculo_id = v.modelos[0]?.id_modelo ?? null;
        form.anio = v.anio ?? '';
        form.consumo_km_gal = v.consumo_km_gal ? Number(v.consumo_km_gal) : '';

        // Los watchers de Vue se ejecutan antes del próximo render; espera ese
        // ciclo antes de volver a habilitarlos.
        await nextTick();
        hidratando = false;
    } catch {
        // Sin datos reales el formulario queda vacío; el usuario puede volver al listado.
    } finally {
        cargando.value = false;
    }
};

onMounted(() => void cargarDatos());

const opcionesMarca = computed(() =>
    marcas.value.map((m) => ({ id: m.id_marca, nombre: m.nombre })),
);
const opcionesEstado = computed(() =>
    estados.value.map((e) => ({
        id: e.id_estado,
        nombre: e.nombre_estado.toLowerCase(),
    })),
);
const opcionesSucursal = computed(() =>
    sucursales.value.map((s) => ({
        id: s.id_sucursal,
        nombre: s.nombre_sucursal,
    })),
);

const tipoSeleccionado = computed(() =>
    tipos.value.find((t) => t.id_tipo_vehiculo === form.tipo_vehiculo_id),
);
const estadoSeleccionado = computed(() =>
    estados.value.find((e) => e.id_estado === form.estado_vehiculo_id),
);
const sucursalSeleccionada = computed(() =>
    sucursales.value.find((s) => s.id_sucursal === form.sucursal_id),
);
const marcaSeleccionada = computed(() =>
    marcas.value.find((m) => m.id_marca === form.marca_vehiculo_id),
);
const modeloSeleccionado = computed(() =>
    modelos.value.find((m) => m.id_modelo === form.modelo_vehiculo_id),
);

// Modelos disponibles según la marca elegida.
const modelosDeMarca = computed(() =>
    modelos.value.filter((m) => m.marca_vehiculo_id === form.marca_vehiculo_id),
);

/* ───────── Tipo según marca/modelo ───────── */
const idTipoMoto = computed(
    () =>
        tipos.value.find((t) => t.nombre === 'Motocicleta')?.id_tipo_vehiculo ??
        null,
);

// Una marca es de motos cuando todos sus modelos del catálogo lo son.
const esMarcaMoto = computed(() => {
    if (form.marca_vehiculo_id === null) return false;
    const modelosMarca = modelosDeMarca.value;

    return (
        modelosMarca.length > 0 &&
        modelosMarca.every(
            (m) =>
                m.tipo_vehiculo_id !== null &&
                m.tipo_vehiculo_id === idTipoMoto.value,
        )
    );
});

// Tipos habilitados según la marca: moto -> solo Motocicleta;
// vehículo -> solo carga (Van, Camion Ligero, Camion Pesado), nunca moto.
const tiposHabilitados = computed(() => {
    if (form.marca_vehiculo_id === null) return tipos.value;
    if (esMarcaMoto.value) {
        return tipos.value.filter(
            (t) => t.id_tipo_vehiculo === idTipoMoto.value,
        );
    }

    return tipos.value.filter((t) => t.id_tipo_vehiculo !== idTipoMoto.value);
});

// Al cambiar la marca, el modelo deja de ser válido y se limpia.
// Durante la hidratación los watchers se omiten (ver hidratando en cargarDatos).
watch(
    () => form.marca_vehiculo_id,
    () => {
        if (hidratando) return;
        form.modelo_vehiculo_id = null;

        // Marca de motos: el tipo Motocicleta se selecciona automáticamente;
        // nunca se asigna otro tipo de forma automática.
        if (esMarcaMoto.value && idTipoMoto.value !== null) {
            form.tipo_vehiculo_id = idTipoMoto.value;
        } else if (
            !tiposHabilitados.value.some(
                (t) => t.id_tipo_vehiculo === form.tipo_vehiculo_id,
            )
        ) {
            form.tipo_vehiculo_id = null;
        }
    },
);

// Al elegir modelo, su tipo de catálogo se asigna automáticamente.
watch(
    () => form.modelo_vehiculo_id,
    () => {
        if (hidratando) return;
        const tipoDelModelo = modeloSeleccionado.value?.tipo_vehiculo_id;
        if (tipoDelModelo) form.tipo_vehiculo_id = tipoDelModelo;
    },
);

const opcionesTipo = computed(() =>
    tiposHabilitados.value.map((t) => ({
        id: t.id_tipo_vehiculo,
        nombre: `${t.nombre} · ${t.marca} · ${fmtNumero(Number(t.capacidad_kg), 0)} kg`,
    })),
);
const opcionesModelo = computed(() =>
    modelosDeMarca.value.map((m) => ({ id: m.id_modelo, nombre: m.nombre })),
);

/* ───────── Validación ───────── */
const checklist = computed(() => [
    { label: 'Placa', ok: FORMATO_PLACA.test(normalizarPlaca(form.placa)) },
    { label: 'Tipo de vehículo', ok: form.tipo_vehiculo_id !== null },
    { label: 'Estado', ok: form.estado_vehiculo_id !== null },
    { label: 'Sucursal base', ok: form.sucursal_id !== null },
    { label: 'Marca', ok: form.marca_vehiculo_id !== null },
]);

const { completados, total, completo } = useChecklist(checklist);

const avisos = computed<Aviso[]>(() => {
    const lista: Aviso[] = [];
    const aviso = (texto: string, grave = false) =>
        lista.push({ texto, grave });

    const placaNormalizada = normalizarPlaca(form.placa);
    if (placaNormalizada && !FORMATO_PLACA.test(placaNormalizada)) {
        aviso(
            'La placa no coincide con el formato nacional: letra (P, C, M, A, N, MB, AB, RE, CD o CC) + 3 a 6 dígitos, por ejemplo P123456.',
            true,
        );
    }

    // Mínimo 1990 y máximo el año en curso, igual que la validación del servidor.
    if (esPositivo(form.anio) && Number(form.anio) > anioActual) {
        aviso('El año no puede ser posterior al año actual.', true);
    }
    if (esPositivo(form.anio) && Number(form.anio) < 1990) {
        aviso('El año debe ser 1990 o posterior.', true);
    }

    if (esPositivo(form.consumo_km_gal) && Number(form.consumo_km_gal) < 3) {
        aviso(
            'El consumo registrado es muy bajo: verifica la unidad (km/gal).',
        );
    }

    if (estadoSeleccionado.value?.nombre_estado === 'INACTIVO') {
        aviso(
            'El vehículo está inactivo: no aparece como disponible para despachos.',
        );
    }

    return lista;
});

/* ───────── Guardar cambios ───────── */
const enviando = ref(false);

const guardar = async () => {
    if (idVehiculo.value === null || !completo.value || enviando.value) return;

    enviando.value = true;
    form.clearErrors();
    form.placa = normalizarPlaca(form.placa);

    try {
        await apiJson(`/api/vehiculos/${idVehiculo.value}`, {
            method: 'PUT',
            body: form.data(),
        });
        router.visit('/vehiculos');
    } catch (error) {
        const apiError = error as ApiError;
        // Errores de validación (422): se muestran junto a cada campo.
        if (apiError.errors) {
            const errores = Object.fromEntries(
                Object.entries(apiError.errors).map(([campo, mensajes]) => [
                    campo,
                    mensajes[0],
                ]),
            );
            form.setError(errores);
        } else {
            form.setError(
                'placa',
                'No se pudo actualizar el vehículo. Intenta nuevamente.',
            );
        }
    } finally {
        enviando.value = false;
    }
};

/* ───────── Desactivar / reactivar (no elimina: preserva el historial) ───────── */
const cambiandoEstado = ref(false);
const mensajeEstado = ref<string | null>(null);

const idEstadoInactivo = computed(
    () =>
        estados.value.find((e) => e.nombre_estado === 'INACTIVO')?.id_estado ??
        null,
);

const estaInactivo = computed(
    () =>
        form.estado_vehiculo_id !== null &&
        form.estado_vehiculo_id === idEstadoInactivo.value,
);

const cambiarEstadoFlota = async (accion: 'desactivar' | 'reactivar') => {
    if (idVehiculo.value === null || cambiandoEstado.value) return;

    const placa = normalizarPlaca(form.placa) || 'este vehículo';
    if (
        accion === 'desactivar' &&
        !confirm(
            `¿Desactivar el vehículo ${placa}? No se elimina: conserva su historial y puedes reactivarlo luego.`,
        )
    ) {
        return;
    }

    cambiandoEstado.value = true;
    try {
        const respuesta = await apiJson<{
            message: string;
            vehiculo: { estado: EstadoVehiculo };
        }>(`/api/vehiculos/${idVehiculo.value}/${accion}`, {
            method: 'POST',
        });
        form.estado_vehiculo_id = respuesta.vehiculo.estado.id_estado;
        mensajeEstado.value = respuesta.message;
    } catch {
        mensajeEstado.value = null;
        alert(`No se pudo ${accion} el vehículo. Intenta nuevamente.`);
    } finally {
        cambiandoEstado.value = false;
    }
};
</script>

<template>
    <Head title="Editar vehículo" />

    <FormPage
        title="Editar vehículo"
        description="Actualiza los datos del vehículo y su estado operativo."
        :crumbs="[
            { label: 'Gestión logística', href: '/vehiculos' },
            { label: 'Vehículos' },
        ]"
        back-href="/vehiculos"
        back-label="Volver a vehículos"
    >
        <!-- Resumen superior -->
        <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <StatCard
                label="Placa"
                :value="form.placa || '—'"
                hint="Identificación única"
            />
            <StatCard
                label="Tipo"
                :value="tipoSeleccionado?.nombre ?? '—'"
                :hint="
                    tipoSeleccionado
                        ? `Capacidad ${fmtNumero(Number(tipoSeleccionado.capacidad_kg), 0)} kg`
                        : 'Sin tipo seleccionado'
                "
            />
            <StatCard
                label="Estado"
                :value="
                    estadoSeleccionado
                        ? estadoSeleccionado.nombre_estado.toLowerCase()
                        : '—'
                "
                hint="Disponibilidad operativa"
            />
            <StatCard
                label="Estado de preparación"
                :value="`${completados}/${total}`"
                :dot="completo ? 'ok' : 'pending'"
                hint="Campos obligatorios"
            />
        </section>

        <form
            class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_350px]"
            @submit.prevent="guardar"
        >
            <div class="min-w-0 space-y-6">
                <!-- 1. Identificación -->
                <FormSection
                    :step="1"
                    title="Identificación"
                    description="Datos generales del vehículo."
                >
                    <div class="grid gap-5 sm:grid-cols-2">
                        <InputField
                            id="placa"
                            v-model="form.placa"
                            label="Placa"
                            maxlength="8"
                            placeholder="P123456"
                            class="uppercase"
                            required
                            :error="form.errors.placa"
                        >
                            <template #hint>
                                Formato nacional: letra (P, C, M, A, N, MB, AB,
                                RE, CD o CC) + 3 a 6 dígitos. Única en la flota.
                            </template>
                        </InputField>
                        <InputField
                            id="anio"
                            v-model="form.anio"
                            label="Año"
                            type="number"
                            min="1990"
                            :max="anioActual"
                            step="1"
                            :placeholder="String(anioActual)"
                            optional
                            :error="form.errors.anio"
                        >
                            <template #hint>
                                Entre 1990 y {{ anioActual }} (año actual).
                            </template>
                        </InputField>
                    </div>
                </FormSection>

                <!-- 2. Clasificación -->
                <FormSection
                    :step="2"
                    title="Clasificación"
                    description="Marca, modelo, tipo de unidad y estado operativo."
                >
                    <div class="grid gap-5 sm:grid-cols-2">
                        <SelectField
                            id="marca"
                            v-model="form.marca_vehiculo_id"
                            label="Marca"
                            placeholder="Selecciona una marca"
                            :options="opcionesMarca"
                            required
                            :error="form.errors.marca_vehiculo_id"
                        />
                        <SelectField
                            id="modelo"
                            v-model="form.modelo_vehiculo_id"
                            label="Modelo"
                            :placeholder="
                                form.marca_vehiculo_id
                                    ? 'Selecciona un modelo'
                                    : 'Elige una marca primero'
                            "
                            :options="opcionesModelo"
                            optional
                            :error="form.errors.modelo_vehiculo_id"
                        />
                        <SelectField
                            id="tipo"
                            v-model="form.tipo_vehiculo_id"
                            label="Tipo de vehículo"
                            placeholder="Selecciona un tipo"
                            :options="opcionesTipo"
                            required
                            :error="form.errors.tipo_vehiculo_id"
                        />
                        <SelectField
                            id="estado"
                            v-model="form.estado_vehiculo_id"
                            label="Estado"
                            placeholder="Selecciona un estado"
                            :options="opcionesEstado"
                            required
                            :error="form.errors.estado_vehiculo_id"
                        />
                    </div>
                </FormSection>

                <!-- 3. Operación -->
                <FormSection
                    :step="3"
                    title="Operación"
                    description="Sucursal base y rendimiento de combustible."
                >
                    <div class="grid gap-5 sm:grid-cols-2">
                        <SelectField
                            id="sucursal"
                            v-model="form.sucursal_id"
                            label="Sucursal base"
                            placeholder="Selecciona una sucursal"
                            :options="opcionesSucursal"
                            required
                            :error="form.errors.sucursal_id"
                        />
                        <InputField
                            id="consumo"
                            v-model="form.consumo_km_gal"
                            label="Consumo"
                            type="number"
                            min="0.01"
                            max="999.99"
                            step="0.01"
                            placeholder="0.00"
                            suffix="km/gal"
                            optional
                            :error="form.errors.consumo_km_gal"
                        >
                            <template #hint>
                                Kilómetros recorridos por litro de combustible.
                            </template>
                        </InputField>
                    </div>
                </FormSection>

                <!-- 4. Desactivar / reactivar (no se elimina: el historial se conserva) -->
                <FormSection
                    :step="4"
                    title="Desactivar vehículo"
                    description="Da de baja el vehículo sin eliminar sus registros históricos."
                >
                    <div
                        class="flex flex-col gap-4 rounded-md border p-4 sm:flex-row sm:items-center sm:justify-between"
                        :class="
                            estaInactivo
                                ? 'border-emerald-200 bg-emerald-50/60'
                                : 'border-red-200 bg-red-50/60'
                        "
                    >
                        <div class="min-w-0">
                            <p
                                class="text-sm font-medium"
                                :class="
                                    estaInactivo
                                        ? 'text-emerald-800'
                                        : 'text-red-800'
                                "
                            >
                                {{
                                    estaInactivo
                                        ? 'Este vehículo está inactivo: no se asigna a nuevos despachos.'
                                        : 'Al desactivar no podrá asignarse a nuevos despachos.'
                                }}
                            </p>
                            <p
                                class="mt-1 text-xs"
                                :class="
                                    estaInactivo
                                        ? 'text-emerald-700/80'
                                        : 'text-red-700/80'
                                "
                            >
                                No se elimina nada: el historial de despachos,
                                paradas y eventos se conserva.
                            </p>
                            <p
                                v-if="mensajeEstado"
                                class="mt-2 text-xs font-medium text-emerald-700"
                            >
                                {{ mensajeEstado }}
                            </p>
                        </div>
                        <button
                            v-if="!estaInactivo"
                            type="button"
                            class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-md border border-red-300 bg-white px-4 text-sm font-semibold text-red-700 shadow-sm transition hover:bg-red-100 focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="cambiandoEstado || cargando"
                            @click="cambiarEstadoFlota('desactivar')"
                        >
                            {{
                                cambiandoEstado
                                    ? 'Desactivando...'
                                    : 'Desactivar vehículo'
                            }}
                        </button>
                        <button
                            v-else
                            type="button"
                            class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-md border border-emerald-300 bg-white px-4 text-sm font-semibold text-emerald-700 shadow-sm transition hover:bg-emerald-100 focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="cambiandoEstado || cargando"
                            @click="cambiarEstadoFlota('reactivar')"
                        >
                            {{
                                cambiandoEstado
                                    ? 'Reactivando...'
                                    : 'Reactivar vehículo'
                            }}
                        </button>
                    </div>
                </FormSection>
            </div>

            <!-- Panel lateral -->
            <aside class="min-w-0 space-y-4 xl:sticky xl:top-6">
                <SummaryPanel
                    title="Resumen del vehículo"
                    subtitle="Vista previa de los cambios"
                >
                    <template #icon
                        ><Truck class="h-5 w-5 text-blue-300"
                    /></template>

                    <SummaryGroup label="Identificación">
                        <dl class="space-y-3">
                            <SummaryItem label="Placa">{{
                                form.placa || 'Sin asignar'
                            }}</SummaryItem>
                            <SummaryItem label="Marca y modelo">{{
                                [
                                    marcaSeleccionada?.nombre,
                                    modeloSeleccionado?.nombre,
                                ]
                                    .filter(Boolean)
                                    .join(' ') || 'Sin asignar'
                            }}</SummaryItem>
                        </dl>
                    </SummaryGroup>

                    <SummaryGroup label="Clasificación">
                        <dl class="space-y-3">
                            <SummaryItem label="Tipo">{{
                                tipoSeleccionado?.nombre ?? 'Sin asignar'
                            }}</SummaryItem>
                            <SummaryItem label="Capacidad">{{
                                tipoSeleccionado
                                    ? `${fmtNumero(Number(tipoSeleccionado.capacidad_kg), 0)} kg`
                                    : '—'
                            }}</SummaryItem>
                            <SummaryItem label="Estado">{{
                                estadoSeleccionado
                                    ? estadoSeleccionado.nombre_estado.toLowerCase()
                                    : 'Sin asignar'
                            }}</SummaryItem>
                        </dl>
                    </SummaryGroup>

                    <SummaryGroup label="Operación">
                        <dl class="space-y-3">
                            <SummaryItem label="Sucursal base">{{
                                sucursalSeleccionada?.nombre_sucursal ??
                                'Sin asignar'
                            }}</SummaryItem>
                            <SummaryItem label="Consumo">{{
                                esPositivo(form.consumo_km_gal)
                                    ? `${fmtNumero(Number(form.consumo_km_gal), 2)} km/gal`
                                    : '—'
                            }}</SummaryItem>
                        </dl>
                    </SummaryGroup>

                    <SummaryGroup
                        ><ChecklistProgress
                            title="Campos obligatorios"
                            :items="checklist"
                    /></SummaryGroup>
                </SummaryPanel>

                <ObservacionesPanel
                    title="Observaciones del vehículo"
                    :avisos="avisos"
                />

                <FormActions
                    submit-label="Guardar cambios"
                    processing-label="Guardando cambios..."
                    :processing="enviando"
                    :disabled="!completo || cargando"
                    cancel-href="/vehiculos"
                    hint="La placa se valida contra toda la flota antes de guardar."
                >
                    <template #icon><Truck class="h-4 w-4" /></template>
                </FormActions>
            </aside>
        </form>
    </FormPage>
</template>
