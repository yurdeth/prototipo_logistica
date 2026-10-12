<script setup lang="ts">
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Users } from '@/UI/Icons';
import { dashboard } from '@/routes';
import { store as guardarRuta } from '@/routes/empleados';
import {
    puestosDemo,
    sucursalesEmpleadoDemo,
    type PuestoCatalogo,
    type SucursalEmpleado,
} from '@/config/Personal/empleados';
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
            { title: 'Empleados', href: '/empleados' },
            { title: 'Nuevo empleado', href: '#' },
        ],
    },
});

const props = withDefaults(
    defineProps<{
        puestos?: PuestoCatalogo[];
        sucursales?: SucursalEmpleado[];
    }>(),
    {
        puestos: () => puestosDemo,
        sucursales: () => sucursalesEmpleadoDemo,
    },
);

/* ───────── Catálogos ───────── */
const departamentos = computed(() => {
    const unicos = new Map<number, string>();
    for (const p of props.puestos) {
        unicos.set(
            p.departamento.id_departamento,
            p.departamento.nombre_departamento,
        );
    }

    return [...unicos].map(([id, nombre]) => ({ id, nombre }));
});
const opcionesDepartamento = computed(() => departamentos.value);
const opcionesPuesto = computed(() =>
    props.puestos
        .filter(
            (p) =>
                form.departamento_id === null ||
                p.departamento.id_departamento === form.departamento_id,
        )
        .map((p) => ({ id: p.id_puesto, nombre: p.nombre_puesto })),
);
const opcionesSucursal = computed(() =>
    props.sucursales.map((s) => ({
        id: s.id_sucursal,
        nombre: s.nombre_sucursal,
    })),
);

/* ───────── Formulario ───────── */
const hoy = new Date().toISOString().slice(0, 10);

const form = useForm({
    nombres: '',
    apellidos: '',
    documento_identidad: '',
    telefono: '',
    departamento_id: null as number | null,
    puesto_id: null as number | null,
    sucursal_id: null as number | null,
    numero_licencia: '',
    fecha_contratacion: hoy,
    activo: true,
});

const puestoSeleccionado = computed(() =>
    props.puestos.find((p) => p.id_puesto === form.puesto_id),
);
const departamentoSeleccionado = computed(() =>
    departamentos.value.find((d) => d.id === form.departamento_id),
);
const sucursalSeleccionada = computed(() =>
    props.sucursales.find((s) => s.id_sucursal === form.sucursal_id),
);

// Al cambiar el departamento, el puesto debe pertenecerle.
const alCambiarDepartamento = () => {
    const puesto = puestoSeleccionado.value;
    if (
        puesto &&
        form.departamento_id !== null &&
        puesto.departamento.id_departamento !== form.departamento_id
    ) {
        form.puesto_id = null;
    }
};

/* ───────── Validación ───────── */
const checklist = computed(() => [
    { label: 'Nombres', ok: form.nombres.trim().length > 0 },
    { label: 'Apellidos', ok: form.apellidos.trim().length > 0 },
    {
        label: 'Documento de identidad',
        ok: form.documento_identidad.trim().length > 0,
    },
    { label: 'Puesto', ok: form.puesto_id !== null },
    { label: 'Sucursal', ok: form.sucursal_id !== null },
]);

const { completados, total, completo } = useChecklist(checklist);

const avisos = computed(() => {
    const lista: { texto: string; grave: boolean }[] = [];
    const aviso = (texto: string, grave = false) =>
        lista.push({ texto, grave });

    if (form.fecha_contratacion && form.fecha_contratacion > hoy) {
        aviso('La fecha de contratación no puede ser futura.', true);
    }

    if (
        puestoSeleccionado.value &&
        !/conductor|motorista|piloto/i.test(
            puestoSeleccionado.value.nombre_puesto,
        ) &&
        form.numero_licencia.trim()
    ) {
        aviso(
            'Registró una licencia en un puesto que no parece de conducción; verifica el dato.',
        );
    }

    return lista;
});

/* ───────── Envío ───────── */
const enviar = () => {
    if (!completo.value || form.processing) return;

    form.clearErrors();
    // Los errores de validación (422) llegan solos al form.errors de Inertia;
    // el controlador redirige al listado cuando el registro sale bien.
    form.post(guardarRuta().url);
};
</script>

<template>
    <Head title="Nuevo empleado" />

    <FormPage
        title="Registrar empleado"
        description="Registra al personal con su puesto y sucursal base."
        :crumbs="[
            { label: 'Gestión logística', href: '/empleados' },
            { label: 'Empleados' },
        ]"
        back-href="/empleados"
        back-label="Volver a empleados"
    >
        <!-- Resumen superior -->
        <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <StatCard
                label="Nombre"
                :value="
                    [form.nombres, form.apellidos].filter(Boolean).join(' ') ||
                    '—'
                "
                hint="Identificación del empleado"
            />
            <StatCard
                label="Puesto"
                :value="puestoSeleccionado?.nombre_puesto ?? '—'"
                :hint="departamentoSeleccionado?.nombre ?? 'Sin departamento'"
            />
            <StatCard
                label="Sucursal"
                :value="sucursalSeleccionada?.nombre_sucursal ?? '—'"
                hint="Base operativa"
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
                <!-- 1. Datos personales -->
                <FormSection
                    :step="1"
                    title="Datos personales"
                    description="Identificación del empleado."
                >
                    <div class="grid gap-5 sm:grid-cols-2">
                        <InputField
                            id="nombres"
                            v-model="form.nombres"
                            label="Nombres"
                            maxlength="100"
                            placeholder="Juan"
                            required
                            :error="form.errors.nombres"
                        />
                        <InputField
                            id="apellidos"
                            v-model="form.apellidos"
                            label="Apellidos"
                            maxlength="100"
                            placeholder="Perez"
                            required
                            :error="form.errors.apellidos"
                        />
                        <InputField
                            id="documento"
                            v-model="form.documento_identidad"
                            label="Documento de identidad"
                            maxlength="20"
                            placeholder="12345678-9"
                            required
                            :error="form.errors.documento_identidad"
                        >
                            <template #hint>
                                Único en todo el personal.
                            </template>
                        </InputField>
                        <InputField
                            id="telefono"
                            v-model="form.telefono"
                            label="Teléfono"
                            maxlength="20"
                            placeholder="70001234"
                            optional
                            :error="form.errors.telefono"
                        />
                    </div>
                </FormSection>

                <!-- 2. Asignación -->
                <FormSection
                    :step="2"
                    title="Asignación"
                    description="Departamento, puesto y sucursal donde labora."
                >
                    <div class="grid gap-5 sm:grid-cols-2">
                        <SelectField
                            id="departamento"
                            v-model="form.departamento_id"
                            label="Departamento"
                            placeholder="Todos los departamentos"
                            :options="opcionesDepartamento"
                            optional
                            :error="form.errors.departamento_id"
                            @update:model-value="alCambiarDepartamento"
                        />
                        <SelectField
                            id="puesto"
                            v-model="form.puesto_id"
                            label="Puesto"
                            placeholder="Selecciona un puesto"
                            :options="opcionesPuesto"
                            required
                            :error="form.errors.puesto_id"
                        />
                        <SelectField
                            id="sucursal"
                            v-model="form.sucursal_id"
                            label="Sucursal"
                            placeholder="Selecciona una sucursal"
                            :options="opcionesSucursal"
                            required
                            :error="form.errors.sucursal_id"
                            class="sm:col-span-2"
                        />
                    </div>
                </FormSection>

                <!-- 3. Detalles operativos -->
                <FormSection
                    :step="3"
                    title="Detalles operativos"
                    description="Licencia, ingreso y disponibilidad."
                >
                    <div class="grid gap-5 sm:grid-cols-2">
                        <InputField
                            id="licencia"
                            v-model="form.numero_licencia"
                            label="Número de licencia"
                            maxlength="30"
                            placeholder="LIC-001"
                            optional
                            :error="form.errors.numero_licencia"
                        >
                            <template #hint>
                                Necesaria para asignar despachos como conductor.
                            </template>
                        </InputField>
                        <InputField
                            id="ingreso"
                            v-model="form.fecha_contratacion"
                            label="Fecha de contratación"
                            type="date"
                            :max="hoy"
                            optional
                            :error="form.errors.fecha_contratacion"
                        />
                        <label
                            class="flex items-center gap-2 self-end text-sm text-slate-700"
                        >
                            <input
                                v-model="form.activo"
                                type="checkbox"
                                class="h-4 w-4 rounded border-slate-300 text-blue-700 focus:ring-blue-600"
                            />
                            Empleado activo
                        </label>
                    </div>
                </FormSection>
            </div>

            <!-- Panel lateral -->
            <aside class="min-w-0 space-y-4 xl:sticky xl:top-6">
                <SummaryPanel
                    title="Resumen del empleado"
                    subtitle="Vista previa del registro"
                >
                    <template #icon
                        ><Users class="h-5 w-5 text-blue-300"
                    /></template>

                    <SummaryGroup label="Datos personales">
                        <dl class="space-y-3">
                            <SummaryItem label="Nombre">{{
                                [form.nombres, form.apellidos]
                                    .filter(Boolean)
                                    .join(' ') || 'Sin asignar'
                            }}</SummaryItem>
                            <SummaryItem label="Documento">{{
                                form.documento_identidad || 'Sin asignar'
                            }}</SummaryItem>
                        </dl>
                    </SummaryGroup>

                    <SummaryGroup label="Asignación">
                        <dl class="space-y-3">
                            <SummaryItem label="Departamento">{{
                                departamentoSeleccionado?.nombre ??
                                'Sin asignar'
                            }}</SummaryItem>
                            <SummaryItem label="Puesto">{{
                                puestoSeleccionado?.nombre_puesto ??
                                'Sin asignar'
                            }}</SummaryItem>
                            <SummaryItem label="Sucursal">{{
                                sucursalSeleccionada?.nombre_sucursal ??
                                'Sin asignar'
                            }}</SummaryItem>
                        </dl>
                    </SummaryGroup>

                    <SummaryGroup label="Detalles">
                        <dl class="space-y-3">
                            <SummaryItem label="Licencia">{{
                                form.numero_licencia || '—'
                            }}</SummaryItem>
                            <SummaryItem label="Ingreso">{{
                                form.fecha_contratacion || '—'
                            }}</SummaryItem>
                            <SummaryItem label="Estado">{{
                                form.activo ? 'Activo' : 'Inactivo'
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
                    title="Observaciones del empleado"
                    :avisos="avisos"
                />

                <FormActions
                    submit-label="Registrar empleado"
                    processing-label="Registrando empleado..."
                    :processing="form.processing"
                    :disabled="!completo"
                    cancel-href="/empleados"
                    hint="El documento de identidad se valida contra todo el personal."
                >
                    <template #icon><Users class="h-4 w-4" /></template>
                </FormActions>
            </aside>
        </form>
    </FormPage>
</template>
