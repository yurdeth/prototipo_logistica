<script setup lang="ts">
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Truck } from '@/UI/Icons';
import { dashboard } from '@/routes';
import { prioridadInfo, type Prioridad } from '@/config/Envios/envios';
import type { ClienteOpcion, Opcion } from '@/config/Envios/envioForm';
import { esPositivo, inputClase, soft } from '@/lib/formularios';
import FormPage from '@/components/Forms/FormPage.vue';
import FormSection from '@/components/Forms/FormSection.vue';
import FormField from '@/components/Forms/FormField.vue';
import SelectField from '@/components/Forms/SelectField.vue';
import InputField from '@/components/Forms/InputField.vue';
import FormActions from '@/components/Forms/FormActions.vue';

interface EnvioEdicion {
    id_envio: number;
    codigo_guia: string;
    cliente_remitente_id: number;
    cliente_destinatario_id: number;
    sucursal_origen_id: number;
    territorio_destino_id: number;
    direccion_destino: string;
    peso_kg: number;
    volumen_m3: number | null;
    prioridad: Prioridad;
    estado: string;
    fecha_comprometida: string;
}

const props = defineProps<{
    envio: EnvioEdicion;
    estados: string[];
    clientes: ClienteOpcion[];
    sucursales: Opcion[];
    territorios: Opcion[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Envíos', href: '/envios' },
            { title: 'Editar envío', href: '#' },
        ],
    },
});

const form = useForm({
    cliente_remitente_id: props.envio.cliente_remitente_id as number | null,
    cliente_destinatario_id: props.envio.cliente_destinatario_id as
        | number
        | null,
    sucursal_origen_id: props.envio.sucursal_origen_id as number | null,
    territorio_destino_id: props.envio.territorio_destino_id as number | null,
    direccion_destino: props.envio.direccion_destino,
    peso_kg: props.envio.peso_kg as number | '',
    volumen_m3: (props.envio.volumen_m3 ?? '') as number | '',
    prioridad: props.envio.prioridad,
    fecha_comprometida: props.envio.fecha_comprometida,
    estado: props.envio.estado,
    nota: '',
});

const prioridades = Object.keys(prioridadInfo) as Prioridad[];

const gruposClientes = computed(() => [
    {
        label: 'Empresas',
        options: props.clientes.filter((c) => c.tipo_cliente === 'EMPRESA'),
    },
    {
        label: 'Personas',
        options: props.clientes.filter((c) => c.tipo_cliente === 'PERSONA'),
    },
]);

const cambioEstado = computed(() => form.estado !== props.envio.estado);

const completo = computed(
    () =>
        form.cliente_remitente_id !== null &&
        form.cliente_destinatario_id !== null &&
        form.sucursal_origen_id !== null &&
        form.territorio_destino_id !== null &&
        form.direccion_destino.trim().length > 0 &&
        esPositivo(form.peso_kg) &&
        form.fecha_comprometida !== '',
);

const guardar = () => {
    if (!completo.value || form.processing) return;
    form.put(`/envios/${props.envio.id_envio}`, { preserveScroll: true });
};
</script>

<template>
    <Head :title="`Editar ${envio.codigo_guia}`" />

    <FormPage
        :title="`Editar envío ${envio.codigo_guia}`"
        description="Actualiza los datos del envío o cambia su estado."
        :crumbs="[
            { label: 'Gestión logística', href: '/envios' },
            { label: 'Envíos', href: '/envios' },
            { label: envio.codigo_guia },
        ]"
        :back-href="`/envios/${envio.id_envio}`"
        back-label="Volver al detalle"
    >
        <form
            class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_350px]"
            @submit.prevent="guardar"
        >
            <div class="min-w-0 space-y-6">
                <FormSection
                    :step="1"
                    title="Estado del envío"
                    description="Cada cambio de estado queda registrado en el historial."
                >
                    <div class="grid gap-5 sm:grid-cols-2">
                        <FormField
                            id="estado"
                            label="Estado"
                            required
                            :error="form.errors.estado"
                        >
                            <select
                                id="estado"
                                v-model="form.estado"
                                required
                                :aria-invalid="!!form.errors.estado"
                                :class="inputClase"
                            >
                                <option
                                    v-for="e in estados"
                                    :key="e"
                                    :value="e"
                                >
                                    {{ e.replace('_', ' ') }}
                                </option>
                            </select>
                        </FormField>

                        <InputField
                            id="nota"
                            v-model="form.nota"
                            label="Nota del cambio"
                            maxlength="500"
                            placeholder="Motivo o detalle del cambio de estado"
                            optional
                            :error="form.errors.nota"
                            :hint="
                                cambioEstado
                                    ? 'Se guardará en el historial junto con el nuevo estado.'
                                    : 'Solo se usa si cambias el estado.'
                            "
                        />
                    </div>
                </FormSection>

                <FormSection
                    :step="2"
                    title="Clientes"
                    description="Quién envía y quién recibe el paquete."
                >
                    <div class="grid gap-5 sm:grid-cols-2">
                        <SearchSelectField
                            id="remitente"
                            v-model="form.cliente_remitente_id"
                            label="Remitente"
                            placeholder="Busca o selecciona un cliente"
                            :groups="gruposClientes"
                            required
                            :error="form.errors.cliente_remitente_id"
                        />
                        <SearchSelectField
                            id="destinatario"
                            v-model="form.cliente_destinatario_id"
                            label="Destinatario"
                            placeholder="Busca o selecciona un cliente"
                            :groups="gruposClientes"
                            required
                            :error="form.errors.cliente_destinatario_id"
                        />
                    </div>
                </FormSection>

                <FormSection
                    :step="3"
                    title="Origen y destino"
                    description="De dónde sale y a dónde se entrega."
                >
                    <div class="grid gap-5 sm:grid-cols-2">
                        <SelectField
                            id="origen"
                            v-model="form.sucursal_origen_id"
                            label="Sucursal de origen"
                            placeholder="Selecciona una sucursal"
                            :options="sucursales"
                            required
                            :error="form.errors.sucursal_origen_id"
                        />
                        <SelectField
                            id="destino"
                            v-model="form.territorio_destino_id"
                            label="Territorio de destino"
                            placeholder="Selecciona un territorio"
                            :options="territorios"
                            required
                            :error="form.errors.territorio_destino_id"
                        />
                        <InputField
                            id="direccion"
                            v-model="form.direccion_destino"
                            class="sm:col-span-2"
                            label="Dirección de entrega"
                            maxlength="255"
                            placeholder="Colonia, calle, número, referencia…"
                            required
                            :error="form.errors.direccion_destino"
                        />
                    </div>
                </FormSection>

                <FormSection
                    :step="4"
                    title="Paquete y compromiso"
                    description="Características físicas, prioridad y fecha límite."
                >
                    <div class="grid gap-5 sm:grid-cols-2">
                        <InputField
                            id="peso"
                            v-model="form.peso_kg"
                            label="Peso"
                            type="number"
                            min="0.01"
                            max="18000"
                            step="0.01"
                            placeholder="0.00"
                            suffix="kg"
                            required
                            :error="form.errors.peso_kg"
                        />
                        <InputField
                            id="volumen"
                            v-model="form.volumen_m3"
                            label="Volumen"
                            type="number"
                            min="0.01"
                            max="100"
                            step="0.01"
                            placeholder="0.00"
                            suffix="m³"
                            optional
                            :error="form.errors.volumen_m3"
                        />

                        <fieldset class="sm:col-span-2">
                            <legend
                                class="mb-1.5 block text-sm font-medium text-slate-700"
                            >
                                Prioridad <span class="text-red-600">*</span>
                            </legend>
                            <div class="grid grid-cols-3 gap-2">
                                <button
                                    v-for="key in prioridades"
                                    :key="key"
                                    type="button"
                                    :aria-pressed="form.prioridad === key"
                                    class="rounded-md border px-3 py-2.5 text-sm font-medium shadow-sm transition-colors focus-visible:ring-2 focus-visible:ring-blue-600/30 focus-visible:outline-none"
                                    :class="
                                        form.prioridad === key
                                            ? ''
                                            : 'border-slate-300 bg-white text-slate-600 hover:bg-slate-50'
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
                                </button>
                            </div>
                            <p
                                v-if="form.errors.prioridad"
                                class="mt-1 text-xs text-red-600"
                            >
                                {{ form.errors.prioridad }}
                            </p>
                        </fieldset>

                        <InputField
                            id="compromiso"
                            v-model="form.fecha_comprometida"
                            class="sm:col-span-2"
                            label="Fecha y hora comprometida"
                            type="datetime-local"
                            required
                            :error="form.errors.fecha_comprometida"
                        />
                    </div>
                </FormSection>
            </div>

            <aside class="min-w-0 space-y-4 xl:sticky xl:top-6">
                <FormActions
                    submit-label="Guardar cambios"
                    processing-label="Guardando cambios..."
                    :processing="form.processing"
                    :disabled="!completo"
                    :cancel-href="`/envios/${envio.id_envio}`"
                    hint="Los cambios de estado se registran en el historial del envío."
                >
                    <template #icon><Truck class="h-4 w-4" /></template>
                </FormActions>
            </aside>
        </form>
    </FormPage>
</template>
