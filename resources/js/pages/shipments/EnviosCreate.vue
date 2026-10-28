<script setup lang="ts">
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Truck } from '@/UI/Icons';
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
import {
    H,
    aInputLocal,
    esPositivo,
    fmtNumero,
    inputClase,
    soft,
    type Aviso,
} from '@/lib/formularios';
import { useChecklist } from '@/composables/useChecklist';
import FormPage from '@/components/Forms/FormPage.vue';
import FormSection from '@/components/Forms/FormSection.vue';
import FormField from '@/components/Forms/FormField.vue';
import StatCard from '@/components/Forms/StatCard.vue';
import SelectField from '@/components/Forms/SelectField.vue';
import SearchSelectField from '@/components/Forms/SearchSelectField.vue';
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
            { title: 'Envíos', href: '/envios' },
            { title: 'Nuevo envío', href: '#' },
        ],
    },
});

// Usa datos de demostración cuando Laravel no proporciona opciones.
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

/* ───────── Formulario ───────── */
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

type TipoCliente = ClienteOpcion['tipo_cliente'];
type FiltroTipoCliente = 'TODOS' | TipoCliente;

const prioridades = Object.keys(prioridadInfo) as Prioridad[];

const tipoRemitente = ref<FiltroTipoCliente>('TODOS');
const tipoDestinatario = ref<FiltroTipoCliente>('TODOS');

const gruposDeClientes = (tipo: FiltroTipoCliente) => {
    const clientes =
        tipo === 'TODOS'
            ? props.clientes
            : props.clientes.filter((cliente) => cliente.tipo_cliente === tipo);

    return [
        {
            label: 'Empresas',
            options: clientes.filter(
                (cliente) => cliente.tipo_cliente === 'EMPRESA',
            ),
        },
        {
            label: 'Personas',
            options: clientes.filter(
                (cliente) => cliente.tipo_cliente === 'PERSONA',
            ),
        },
    ];
};

const gruposRemitente = computed(() => gruposDeClientes(tipoRemitente.value));
const gruposDestinatario = computed(() =>
    gruposDeClientes(tipoDestinatario.value),
);

watch(tipoRemitente, (tipo) => {
    const seleccionado = props.clientes.find(
        (cliente) => cliente.id === form.cliente_remitente_id,
    );
    if (
        seleccionado &&
        tipo !== 'TODOS' &&
        seleccionado.tipo_cliente !== tipo
    ) {
        form.cliente_remitente_id = null;
    }
});

watch(tipoDestinatario, (tipo) => {
    const seleccionado = props.clientes.find(
        (cliente) => cliente.id === form.cliente_destinatario_id,
    );
    if (
        seleccionado &&
        tipo !== 'TODOS' &&
        seleccionado.tipo_cliente !== tipo
    ) {
        form.cliente_destinatario_id = null;
    }
});

/* ───────── Compromiso de entrega ───────── */
// El reloj arranca en el montaje (cliente): con SSR, un Date.now() tomado en
// el render de servidor difiere del del cliente y rompe la hidratación.
const ahora = ref<number | null>(null);
let reloj: ReturnType<typeof setInterval> | undefined;

const sugerida = computed(() =>
    ahora.value === null
        ? ''
        : aInputLocal(
              new Date(ahora.value + horasCompromiso[form.prioridad] * H),
          ),
);
const minimo = computed(() =>
    ahora.value === null ? '' : aInputLocal(new Date(ahora.value)),
);
const manual = ref(false);

const usarSugerida = () => {
    manual.value = false;
    form.fecha_comprometida = sugerida.value;
};

onMounted(() => {
    ahora.value = Date.now();
    usarSugerida();
    // Refresca los plazos para no mostrar información desactualizada.
    reloj = setInterval(() => (ahora.value = Date.now()), 60_000);
});

onUnmounted(() => reloj && clearInterval(reloj));

// Actualiza la fecha sugerida solo si el usuario no la modificó.
watch(
    () => form.prioridad,
    () => {
        if (!manual.value) usarSugerida();
    },
);

/* ───────── Resumen ───────── */
const nombreDe = (lista: Opcion[], id: number | null) =>
    lista.find((o) => o.id === id)?.nombre ?? null;

const remitente = computed(() =>
    nombreDe(props.clientes, form.cliente_remitente_id),
);
const destinatario = computed(() =>
    nombreDe(props.clientes, form.cliente_destinatario_id),
);
const origen = computed(() =>
    nombreDe(props.sucursales, form.sucursal_origen_id),
);
const destino = computed(() =>
    nombreDe(props.territorios, form.territorio_destino_id),
);

const compromisoMs = computed(() =>
    form.fecha_comprometida ? new Date(form.fecha_comprometida).getTime() : NaN,
);

const horas = computed(() =>
    ahora.value !== null && Number.isFinite(compromisoMs.value)
        ? (compromisoMs.value - ahora.value) / H
        : null,
);

const compromisoTexto = computed(() =>
    horas.value === null
        ? null
        : new Date(compromisoMs.value).toLocaleString('es-SV', {
              weekday: 'short',
              day: '2-digit',
              month: 'short',
              hour: '2-digit',
              minute: '2-digit',
          }),
);

const plazoTexto = computed(() => {
    if (horas.value === null) return '—';
    if (horas.value <= 0) return 'fecha ya vencida';

    return horas.value >= 48
        ? `en ${(horas.value / 24).toFixed(1)} días`
        : `en ${Math.ceil(horas.value)} horas`;
});

const pesoTexto = computed(() =>
    esPositivo(form.peso_kg) ? `${Number(form.peso_kg).toFixed(2)} kg` : '—',
);
const volumenTexto = computed(() =>
    esPositivo(form.volumen_m3)
        ? `${Number(form.volumen_m3).toFixed(2)} m³`
        : '—',
);

/* ───────── Validación ───────── */
const checklist = computed(() => [
    { label: 'Remitente', ok: form.cliente_remitente_id !== null },
    { label: 'Destinatario', ok: form.cliente_destinatario_id !== null },
    { label: 'Sucursal de origen', ok: form.sucursal_origen_id !== null },
    { label: 'Territorio de destino', ok: form.territorio_destino_id !== null },
    {
        label: 'Dirección de entrega',
        ok: form.direccion_destino.trim().length > 0,
    },
    { label: 'Peso', ok: esPositivo(form.peso_kg) },
    {
        label: 'Compromiso de entrega',
        ok: horas.value !== null && horas.value > 0,
    },
]);

const { completados, total, completo } = useChecklist(checklist);

const avisos = computed<Aviso[]>(() => {
    const lista: Aviso[] = [];
    const h = horas.value;
    const aviso = (texto: string) => lista.push({ texto, grave: false });

    if (
        form.cliente_remitente_id !== null &&
        form.cliente_remitente_id === form.cliente_destinatario_id
    ) {
        aviso('El remitente y el destinatario son el mismo cliente.');
    }

    if (esPositivo(form.peso_kg) && Number(form.peso_kg) >= PESO_CARGA_PESADA) {
        aviso(
            `Carga pesada (${PESO_CARGA_PESADA} kg o más): verifica la capacidad del vehículo.`,
        );
    }

    if (form.prioridad === 'URGENTE' && h !== null && h > 48) {
        aviso('La prioridad urgente tiene un compromiso superior a 48 horas.');
    }

    if (form.prioridad === 'BAJA' && h !== null && h > 0 && h < 48) {
        aviso('La prioridad baja tiene un plazo inferior a 48 horas.');
    }

    return lista;
});

/* ───────── Envío ───────── */
const enviar = () => {
    if (!completo.value || form.processing) return;
    form.post('/envios', { preserveScroll: true });
};
</script>

<template>
    <Head title="Nuevo envío" />

    <FormPage
        title="Registrar envío"
        description="Registra el envío y define su compromiso de entrega."
        :crumbs="[
            { label: 'Gestión logística', href: '/envios' },
            { label: 'Envíos' },
        ]"
        back-href="/envios"
        back-label="Volver a envíos"
    >
        <!-- Resumen superior -->
        <section class="grid grid-cols-2 gap-3 lg:grid-cols-4">
            <StatCard
                label="Peso"
                :value="
                    esPositivo(form.peso_kg)
                        ? fmtNumero(Number(form.peso_kg), 2)
                        : '—'
                "
                unit="kg"
                hint="Carga del envío"
            />
            <StatCard
                label="Prioridad"
                :value="prioridadInfo[form.prioridad].label"
                :hint="`Plazo base de ${horasCompromiso[form.prioridad]} h`"
            />
            <StatCard
                label="Entrega"
                :value="plazoTexto"
                :hint="compromisoTexto ?? 'Sin fecha definida'"
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
                <!-- 1. Clientes -->
                <FormSection
                    :step="1"
                    title="Clientes"
                    description="Quién envía y quién recibe el paquete."
                >
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="space-y-4">
                            <FormField
                                id="tipo-remitente"
                                label="Tipo de remitente"
                            >
                                <select
                                    id="tipo-remitente"
                                    v-model="tipoRemitente"
                                    :class="inputClase"
                                >
                                    <option value="TODOS">
                                        Todos los tipos
                                    </option>
                                    <option value="PERSONA">Persona</option>
                                    <option value="EMPRESA">Empresa</option>
                                </select>
                            </FormField>
                            <SearchSelectField
                                id="remitente"
                                v-model="form.cliente_remitente_id"
                                label="Remitente"
                                placeholder="Busca o selecciona un cliente"
                                :groups="gruposRemitente"
                                required
                                :error="form.errors.cliente_remitente_id"
                            />
                        </div>
                        <div class="space-y-4">
                            <FormField
                                id="tipo-destinatario"
                                label="Tipo de destinatario"
                            >
                                <select
                                    id="tipo-destinatario"
                                    v-model="tipoDestinatario"
                                    :class="inputClase"
                                >
                                    <option value="TODOS">
                                        Todos los tipos
                                    </option>
                                    <option value="PERSONA">Persona</option>
                                    <option value="EMPRESA">Empresa</option>
                                </select>
                            </FormField>
                            <SearchSelectField
                                id="destinatario"
                                v-model="form.cliente_destinatario_id"
                                label="Destinatario"
                                placeholder="Busca o selecciona un cliente"
                                :groups="gruposDestinatario"
                                required
                                :error="form.errors.cliente_destinatario_id"
                            />
                        </div>
                    </div>
                </FormSection>

                <!-- 2. Origen y destino -->
                <FormSection
                    :step="2"
                    title="Origen y destino"
                    description="Define de dónde sale y a dónde se entrega."
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

                <!-- 3. Paquete -->
                <FormSection
                    :step="3"
                    title="Paquete"
                    description="Características físicas y prioridad del envío."
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
                                    <span
                                        class="block text-[11px] font-normal opacity-80"
                                        >{{ horasCompromiso[key] }} h</span
                                    >
                                </button>
                            </div>
                            <p
                                v-if="form.errors.prioridad"
                                class="mt-1 text-xs text-red-600"
                            >
                                {{ form.errors.prioridad }}
                            </p>
                        </fieldset>
                    </div>
                </FormSection>

                <!-- 4. Compromiso de entrega -->
                <FormSection
                    :step="4"
                    title="Compromiso de entrega"
                    description="Fecha y hora límite para entregar el paquete."
                >
                    <div class="grid gap-5 sm:grid-cols-2">
                        <InputField
                            id="compromiso"
                            v-model="form.fecha_comprometida"
                            label="Fecha y hora comprometida"
                            type="datetime-local"
                            :min="minimo"
                            required
                            :error="form.errors.fecha_comprometida"
                            @input="manual = true"
                        >
                            <template #hint>
                                Plazo sugerido según la prioridad ({{
                                    horasCompromiso[form.prioridad]
                                }}
                                h).
                                <button
                                    v-if="manual"
                                    type="button"
                                    class="ml-1 font-medium text-blue-700 hover:underline"
                                    @click="usarSugerida"
                                >
                                    Restablecer sugerida
                                </button>
                            </template>
                        </InputField>
                    </div>
                </FormSection>
            </div>

            <!-- Panel lateral -->
            <aside class="min-w-0 space-y-4 xl:sticky xl:top-6">
                <SummaryPanel
                    title="Resumen del envío"
                    subtitle="Vista previa del registro"
                >
                    <template #icon
                        ><Truck class="h-5 w-5 text-blue-300"
                    /></template>

                    <SummaryGroup label="Clientes">
                        <dl class="space-y-3">
                            <SummaryItem label="Remitente">{{
                                remitente ?? 'Sin asignar'
                            }}</SummaryItem>
                            <SummaryItem label="Destinatario">{{
                                destinatario ?? 'Sin asignar'
                            }}</SummaryItem>
                        </dl>
                    </SummaryGroup>

                    <SummaryGroup label="Ruta">
                        <dl class="space-y-3">
                            <SummaryItem label="Origen">{{
                                origen ?? 'Sin asignar'
                            }}</SummaryItem>
                            <SummaryItem label="Destino">{{
                                destino ?? 'Sin asignar'
                            }}</SummaryItem>
                        </dl>
                    </SummaryGroup>

                    <SummaryGroup label="Paquete">
                        <dl class="grid grid-cols-3 gap-4">
                            <SummaryItem label="Peso">{{
                                pesoTexto
                            }}</SummaryItem>
                            <SummaryItem label="Volumen">{{
                                volumenTexto
                            }}</SummaryItem>
                            <SummaryItem
                                label="Prioridad"
                                :value-class="
                                    form.prioridad === 'URGENTE'
                                        ? 'text-red-600'
                                        : undefined
                                "
                            >
                                {{ prioridadInfo[form.prioridad].label }}
                            </SummaryItem>
                        </dl>
                    </SummaryGroup>

                    <SummaryGroup label="Entrega comprometida">
                        <p class="text-sm font-semibold text-slate-900">
                            {{ compromisoTexto ?? '—' }}
                        </p>
                        <p class="mt-1 text-sm text-slate-500">
                            {{ plazoTexto }}
                        </p>
                    </SummaryGroup>

                    <SummaryGroup
                        ><ChecklistProgress
                            title="Campos obligatorios"
                            :items="checklist"
                    /></SummaryGroup>
                </SummaryPanel>

                <ObservacionesPanel
                    title="Observaciones del envío"
                    :avisos="avisos"
                />

                <FormActions
                    submit-label="Registrar envío"
                    processing-label="Registrando envío..."
                    :processing="form.processing"
                    :disabled="!completo"
                    cancel-href="/envios"
                    hint="Verifica los datos y el plazo de entrega antes de confirmar."
                >
                    <template #icon><Truck class="h-4 w-4" /></template>
                </FormActions>
            </aside>
        </form>
    </FormPage>
</template>
