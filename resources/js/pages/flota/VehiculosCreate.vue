<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Truck } from '@/UI/Icons';
import { dashboard } from '@/routes';
import { store as guardarRuta } from '@/routes/vehiculos';
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
} from '@/config/Flota/vehiculos';
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
            { title: 'Nuevo vehículo', href: '#' },
        ],
    },
});

// Usa catálogos de demostración mientras el endpoint no responde.
const props = withDefaults(
    defineProps<{
        tipos?: TipoVehiculo[];
        estados?: EstadoVehiculo[];
        sucursales?: SucursalVehiculo[];
        marcas?: MarcaVehiculoCatalogo[];
        modelos?: ModeloVehiculoCatalogo[];
    }>(),
    {
        tipos: () => tiposVehiculoDemo,
        estados: () => estadosVehiculoDemo,
        sucursales: () => sucursalesVehiculoDemo,
        marcas: () => marcasVehiculoDemo,
        modelos: () => modelosVehiculoDemo,
    },
);

/* ───────── Catálogos (props del servidor; demo como respaldo) ───────── */
const tipos = ref<TipoVehiculo[]>(props.tipos);
const estados = ref<EstadoVehiculo[]>(props.estados);
const sucursales = ref<SucursalVehiculo[]>(props.sucursales);
const marcas = ref<MarcaVehiculoCatalogo[]>(props.marcas);
const modelos = ref<ModeloVehiculoCatalogo[]>(props.modelos);

const opcionesTipo = computed(() =>
    tiposHabilitados.value.map((t) => ({
        id: t.id_tipo_vehiculo,
        nombre: `${t.nombre} · ${t.marca} · ${fmtNumero(Number(t.capacidad_kg), 0)} kg`,
    })),
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
// Id del tipo Motocicleta dentro del catálogo cargado.
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
watch(
    () => form.marca_vehiculo_id,
    () => {
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
        const tipoDelModelo = modeloSeleccionado.value?.tipo_vehiculo_id;
        if (tipoDelModelo) form.tipo_vehiculo_id = tipoDelModelo;
    },
);

const opcionesMarca = computed(() =>
    marcas.value.map((m) => ({ id: m.id_marca, nombre: m.nombre })),
);
const opcionesModelo = computed(() =>
    modelosDeMarca.value.map((m) => ({ id: m.id_modelo, nombre: m.nombre })),
);

/* ───────── Validación ───────── */
// FORMATO_PLACA y normalizarPlaca vienen de @/config/Flota/vehiculos, donde está
// documentada la categoría de cada letra del formato nacional de placas.
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
            'La placa no coincide con el formato nacional: letra (P, C, M, A, N, MB, AB, RE, CD o CC) + hasta 6 caracteres alfanuméricos, por ejemplo P123456.',
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

    if (
        tipoSeleccionado.value &&
        estadoSeleccionado.value?.nombre_estado === 'MANTENIMIENTO'
    ) {
        aviso('El vehículo se registrará como no disponible para despacho.');
    }

    return lista;
});

/* ───────── Envío ───────── */
const enviando = computed(() => form.processing);

const enviar = () => {
    if (!completo.value || enviando.value) return;

    form.clearErrors();
    form.placa = normalizarPlaca(form.placa);
    // Los errores de validación (422) llegan solos al form.errors de Inertia;
    // el controlador redirige al listado cuando el registro sale bien.
    form.post(guardarRuta().url);
};
</script>

<template>
    <Head title="Nuevo vehículo" />

    <FormPage
        title="Registrar vehículo"
        description="Registra el vehículo y define su tipo, estado y base operativa."
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
            @submit.prevent="enviar"
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
                                RE, CD o CC) + hasta 6 caracteres. Única en la
                                flota.
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
            </div>

            <!-- Panel lateral -->
            <aside class="min-w-0 space-y-4 xl:sticky xl:top-6">
                <SummaryPanel
                    title="Resumen del vehículo"
                    subtitle="Vista previa del registro"
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
                    submit-label="Registrar vehículo"
                    processing-label="Registrando vehículo..."
                    :processing="enviando"
                    :disabled="!completo"
                    cancel-href="/vehiculos"
                    hint="La placa se valida contra toda la flota antes de guardar."
                >
                    <template #icon><Truck class="h-4 w-4" /></template>
                </FormActions>
            </aside>
        </form>
    </FormPage>
</template>
