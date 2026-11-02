<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Users } from '@/UI/Icons';
import { dashboard } from '@/routes';
import {
    update as actualizarRuta,
    edit as editarRuta,
    desactivar as desactivarRuta,
    reactivar as reactivarRuta,
} from '@/routes/empleados';
import {
    puestosDemo,
    sucursalesEmpleadoDemo,
    type Empleado,
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
            { title: 'Editar empleado', href: '#' },
        ],
    },
});

const props = withDefaults(
    defineProps<{
        puestos?: PuestoCatalogo[];
        sucursales?: SucursalEmpleado[];
        empleado?: Empleado;
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

/* ───────── Formulario hidratado desde el controlador ───────── */
const hoy = new Date().toISOString().slice(0, 10);

const form = useForm({
    nombres: props.empleado?.nombres ?? '',
    apellidos: props.empleado?.apellidos ?? '',
    documento_identidad: props.empleado?.documento_identidad ?? '',
    telefono: props.empleado?.telefono ?? '',
    departamento_id:
        props.empleado?.puesto.departamento.id_departamento ?? null,
    puesto_id: props.empleado?.puesto.id_puesto ?? null,
    sucursal_id: props.empleado?.sucursal.id_sucursal ?? null,
    numero_licencia: props.empleado?.numero_licencia ?? '',
    // slice(0,10): el <input type="date"> exige YYYY-MM-DD, no datetime ISO
    fecha_contratacion: props.empleado?.fecha_contratacion?.slice(0, 10) ?? hoy,
    activo: props.empleado?.activo ?? true,
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
    {
        label: 'Número de licencia',
        ok: form.numero_licencia.trim().length > 0,
    },
]);

const { completados, total, completo } = useChecklist(checklist);

const avisos = computed(() => {
    const lista: { texto: string; grave: boolean }[] = [];
    const aviso = (texto: string, grave = false) =>
        lista.push({ texto, grave });

    if (form.fecha_contratacion && form.fecha_contratacion > hoy) {
        aviso('La fecha de contratación no puede ser futura.', true);
    }

    if (!form.activo) {
        aviso(
            'El empleado está inactivo: no podrá asignarse a nuevos despachos.',
        );
    }

    return lista;
});

/* ───────── Envío ───────── */
const enviar = () => {
    if (!completo.value || form.processing || !props.empleado) return;

    form.clearErrors();
    // Los errores de validación (422) llegan solos al form.errors de Inertia;
    // el controlador redirige al listado cuando la actualización sale bien.
    form.put(actualizarRuta({ empleado: props.empleado.id_empleado }).url);
};

/* ───────── Desactivar / reactivar (no elimina: preserva el historial) ───────── */
const cambiandoEstado = ref(false);
const mensajeEstado = ref<string | null>(null);

const cambiarEstadoEmpleado = (accion: 'desactivar' | 'reactivar') => {
    if (!props.empleado || cambiandoEstado.value) return;

    const nombre = `${props.empleado.nombres} ${props.empleado.apellidos}`;
    if (
        accion === 'desactivar' &&
        !confirm(
            `¿Desactivar a ${nombre}? No se elimina: conserva su historial y puedes reactivarlo luego.`,
        )
    ) {
        return;
    }

    cambiandoEstado.value = true;
    const ruta =
        accion === 'desactivar'
            ? desactivarRuta({ empleado: props.empleado.id_empleado })
            : reactivarRuta({ empleado: props.empleado.id_empleado });

    router.post(
        ruta.url,
        {},
        {
            preserveScroll: true,
            onSuccess: (pagina) => {
                const e = pagina.props.empleado as
                    | { activo: boolean }
                    | undefined;
                if (e) form.activo = e.activo;
                mensajeEstado.value =
                    accion === 'desactivar'
                        ? `${nombre} desactivado. Conserva su historial.`
                        : `${nombre} reactivado y disponible para despachos.`;
            },
            onError: () => {
                mensajeEstado.value = null;
                alert(`No se pudo ${accion} el empleado. Intenta nuevamente.`);
            },
            onFinish: () => {
                cambiandoEstado.value = false;
            },
        },
    );
};
</script>

<template>
    <Head title="Editar empleado" />

    <FormPage
        title="Editar empleado"
        description="Actualiza los datos, el puesto y la sucursal del empleado."
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
                    description="Licencia e ingreso."
                >
                    <div class="grid gap-5 sm:grid-cols-2">
                        <InputField
                            id="licencia"
                            v-model="form.numero_licencia"
                            label="Número de licencia"
                            maxlength="30"
                            placeholder="LIC-001"
                            required
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
                    </div>
                </FormSection>

                <!-- 4. Desactivar / reactivar (no se elimina: el historial se conserva) -->
                <FormSection
                    :step="4"
                    title="Desactivar empleado"
                    description="Da de baja al empleado sin eliminar sus registros históricos."
                >
                    <div
                        class="flex flex-col gap-4 rounded-md border p-4 sm:flex-row sm:items-center sm:justify-between"
                        :class="
                            form.activo
                                ? 'border-red-200 bg-red-50/60'
                                : 'border-emerald-200 bg-emerald-50/60'
                        "
                    >
                        <div class="min-w-0">
                            <p
                                class="text-sm font-medium"
                                :class="
                                    form.activo
                                        ? 'text-red-800'
                                        : 'text-emerald-800'
                                "
                            >
                                {{
                                    form.activo
                                        ? 'Al desactivar no podrá asignarse a nuevos despachos.'
                                        : 'Este empleado está inactivo: no se asigna a nuevos despachos.'
                                }}
                            </p>
                            <p
                                class="mt-1 text-xs"
                                :class="
                                    form.activo
                                        ? 'text-red-700/80'
                                        : 'text-emerald-700/80'
                                "
                            >
                                No se elimina nada: su historial de despachos y
                                registros se conserva.
                            </p>
                            <p
                                v-if="mensajeEstado"
                                class="mt-2 text-xs font-medium text-emerald-700"
                            >
                                {{ mensajeEstado }}
                            </p>
                        </div>
                        <button
                            v-if="form.activo"
                            type="button"
                            class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-md border border-red-300 bg-white px-4 text-sm font-semibold text-red-700 shadow-sm transition hover:bg-red-100 focus-visible:ring-2 focus-visible:ring-red-600 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="cambiandoEstado"
                            @click="cambiarEstadoEmpleado('desactivar')"
                        >
                            {{
                                cambiandoEstado
                                    ? 'Desactivando...'
                                    : 'Desactivar empleado'
                            }}
                        </button>
                        <button
                            v-else
                            type="button"
                            class="inline-flex h-10 shrink-0 items-center justify-center gap-2 rounded-md border border-emerald-300 bg-white px-4 text-sm font-semibold text-emerald-700 shadow-sm transition hover:bg-emerald-100 focus-visible:ring-2 focus-visible:ring-emerald-600 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="cambiandoEstado"
                            @click="cambiarEstadoEmpleado('reactivar')"
                        >
                            {{
                                cambiandoEstado
                                    ? 'Reactivando...'
                                    : 'Reactivar empleado'
                            }}
                        </button>
                    </div>
                </FormSection>
            </div>

            <!-- Panel lateral -->
            <aside class="min-w-0 space-y-4 xl:sticky xl:top-6">
                <SummaryPanel
                    title="Resumen del empleado"
                    subtitle="Vista previa de los cambios"
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
                    submit-label="Guardar cambios"
                    processing-label="Guardando cambios..."
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
