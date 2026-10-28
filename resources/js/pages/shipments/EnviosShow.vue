<script setup lang="ts">
import { computed, onBeforeUnmount, ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { dashboard } from '@/routes';
import { estadoInfo, prioridadInfo, slaInfo } from '@/config/Envios/envios';
import { getSla, fmtFecha } from '@/composables/useEnviosBI';
import { useClipboard } from '@vueuse/core';
import { Check, Copy } from '@lucide/vue';
import BiFields from '@/components/bi/DetailFields.vue';
import BiTimeline from '@/components/bi/BiTimeline.vue';
import BiBadge from '@/components/bi/BiBadge.vue';
import BiStatus from '@/components/bi/BiStatus.vue';
import FormField from '@/components/Forms/FormField.vue';
import InputField from '@/components/Forms/InputField.vue';
import { inputClase } from '@/lib/formularios';
import type { Field, TimelineItem } from '@/components/bi/Utils';

interface EventoEnvio {
    id_evento: number;
    estado: keyof typeof estadoInfo;
    ocurrido_en: string;
    nota: string | null;
    responsable: string | null;
    latitud: number | null;
    longitud: number | null;
}

interface DetalleEnvio {
    id_envio: number;
    codigo_guia: string;
    remitente: string;
    destinatario: string;
    sucursal_origen: string;
    territorio_destino: string;
    direccion_destino: string;
    peso_kg: number;
    volumen_m3: number | null;
    estado: keyof typeof estadoInfo;
    prioridad: keyof typeof prioridadInfo;
    fecha_registro: string;
    fecha_comprometida: string;
    fecha_entrega: string | null;
}

const props = defineProps<{
    envio: DetalleEnvio;
    eventos: EventoEnvio[];
    estados: string[];
    permisos: { registrar: boolean; editar: boolean };
}>();

defineOptions({
    layout: {
        breadcrumbs: [
            { title: 'Dashboard', href: dashboard() },
            { title: 'Envíos', href: '/envios' },
            { title: 'Detalle', href: '#' },
        ],
    },
});

// getSla espera la forma del envío del índice; el detalle trae los mismos campos que usa.
const sla = computed(() => getSla(props.envio as never, Date.now()));
const esAlerta = computed(() => ['vencido', 'en_riesgo'].includes(sla.value));

const personas = computed<Field[]>(() => [
    { label: 'Remitente', value: props.envio.remitente, weight: 'medium' },
    {
        label: 'Destinatario',
        value: props.envio.destinatario,
        weight: 'medium',
    },
]);

const logistica = computed<Field[]>(() => [
    { label: 'Sucursal de origen', value: props.envio.sucursal_origen },
    { label: 'Destino', value: props.envio.territorio_destino },
    {
        label: 'Dirección de entrega',
        value: props.envio.direccion_destino,
        wide: true,
    },
]);

const especificaciones = computed<Field[]>(() => [
    {
        label: 'Peso',
        value: `${props.envio.peso_kg.toFixed(2)} kg`,
        weight: 'semibold',
    },
    {
        label: 'Volumen',
        value: props.envio.volumen_m3 ? `${props.envio.volumen_m3} m³` : '—',
        weight: 'semibold',
    },
    { label: 'Fecha de registro', value: fmtFecha(props.envio.fecha_registro) },
    {
        label: 'Fecha compromiso',
        value: fmtFecha(props.envio.fecha_comprometida),
    },
    {
        label: 'Fecha de entrega',
        value: props.envio.fecha_entrega
            ? fmtFecha(props.envio.fecha_entrega)
            : '—',
    },
]);

const historial = computed<TimelineItem[]>(() =>
    props.eventos.map((ev) => ({
        key: ev.id_evento,
        color: estadoInfo[ev.estado].color,
        title: estadoInfo[ev.estado].label,
        // Fecha y hora, y quién hizo el cambio
        meta:
            fmtFecha(ev.ocurrido_en) +
            (ev.responsable ? ` · ${ev.responsable}` : ''),
        note: ev.nota || undefined,
        // Ubicación del evento, si se registró
        tag:
            ev.latitud !== null && ev.longitud !== null
                ? `${ev.latitud.toFixed(5)}, ${ev.longitud.toFixed(5)}`
                : undefined,
    })),
);

/* ───────── Copiar código de guía ───────── */
const { copy } = useClipboard();
const codigoCopiado = ref(false);
let copiadoTimer: ReturnType<typeof setTimeout> | undefined;

const copiarGuia = async () => {
    await copy(props.envio.codigo_guia);
    codigoCopiado.value = true;
    clearTimeout(copiadoTimer);
    copiadoTimer = setTimeout(() => (codigoCopiado.value = false), 2000);
};

onBeforeUnmount(() => clearTimeout(copiadoTimer));

/* ───────── Cambio de estado ───────── */
const form = useForm({
    estado: props.envio.estado as string,
    nota: '',
    latitud: '' as number | '',
    longitud: '' as number | '',
});

// Al guardar, Inertia vuelve a pedir la página: el nuevo evento aparece sin recargar a mano.
const cambiarEstado = () => {
    if (form.processing || form.estado === props.envio.estado) return;

    form.patch(`/envios/${props.envio.id_envio}/estado`, {
        preserveScroll: true,
        onSuccess: () => form.reset('nota', 'latitud', 'longitud'),
    });
};
</script>

<template>
    <Head :title="`Envío ${envio.codigo_guia}`" />

    <div class="mx-auto flex w-full max-w-4xl flex-col gap-4 p-4">
        <header
            class="flex flex-col justify-between gap-3 sm:flex-row sm:items-center"
        >
            <div>
                <p class="text-sm text-muted-foreground">Detalle del envío</p>
                <h1
                    class="flex items-center gap-2 font-mono text-2xl font-semibold text-foreground"
                >
                    {{ envio.codigo_guia }}
                    <button
                        type="button"
                        class="rounded p-1 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                        aria-label="Copiar código de guía"
                        :title="codigoCopiado ? 'Copiado' : 'Copiar código'"
                        @click="copiarGuia"
                    >
                        <Check
                            v-if="codigoCopiado"
                            class="h-4 w-4 text-green-500"
                        />
                        <Copy v-else class="h-4 w-4" />
                    </button>
                </h1>
            </div>

            <div class="flex gap-2">
                <Link
                    href="/envios"
                    class="inline-flex h-10 items-center rounded-md border border-sidebar-border/70 bg-card px-4 text-sm font-medium text-foreground transition hover:bg-muted"
                >
                    Volver a envíos
                </Link>
                <Link
                    v-if="permisos.editar"
                    :href="`/envios/${envio.id_envio}/editar`"
                    class="inline-flex h-10 items-center rounded-md bg-blue-700 px-4 text-sm font-semibold text-white transition hover:bg-blue-800"
                >
                    Editar envío
                </Link>
            </div>
        </header>

        <section
            class="flex flex-wrap items-center gap-2 rounded-xl border border-sidebar-border/70 bg-card p-4 shadow-sm"
        >
            <BiStatus
                class="rounded-lg border border-sidebar-border/70 bg-card px-3 py-2 text-sm font-medium text-foreground"
                :color="estadoInfo[envio.estado].color"
                :label="estadoInfo[envio.estado].label"
            />
            <span
                class="rounded-lg border border-sidebar-border/70 bg-card px-3 py-2 text-sm text-foreground"
            >
                Prioridad
                {{ prioridadInfo[envio.prioridad].label.toLowerCase() }}
            </span>
            <BiBadge
                v-if="esAlerta"
                size="md"
                :color="slaInfo[sla].color"
                :label="slaInfo[sla].label"
            />
        </section>

        <section
            class="space-y-5 rounded-xl border border-sidebar-border/70 bg-card p-4 shadow-sm sm:p-5"
        >
            <BiFields
                title="Información general"
                :fields="personas"
                responsive
            />
            <BiFields title="Información logística" :fields="logistica" />
            <BiFields
                title="Especificaciones y fechas"
                :fields="especificaciones"
            />
        </section>

        <section
            v-if="permisos.editar"
            class="rounded-xl border border-sidebar-border/70 bg-card p-4 shadow-sm sm:p-5"
        >
            <h2 class="text-sm font-semibold text-foreground">
                Cambiar estado
            </h2>
            <p class="mt-1 text-sm text-muted-foreground">
                Cada cambio queda registrado en el historial con tu usuario, la
                fecha y la ubicación si la indicas.
            </p>

            <form
                class="mt-4 grid gap-4 sm:grid-cols-2"
                @submit.prevent="cambiarEstado"
            >
                <FormField
                    id="nuevo-estado"
                    label="Nuevo estado"
                    required
                    :error="form.errors.estado"
                >
                    <select
                        id="nuevo-estado"
                        v-model="form.estado"
                        required
                        :aria-invalid="!!form.errors.estado"
                        :class="inputClase"
                    >
                        <option v-for="e in estados" :key="e" :value="e">
                            {{ e.replace('_', ' ') }}
                        </option>
                    </select>
                </FormField>

                <InputField
                    id="nota-estado"
                    v-model="form.nota"
                    label="Nota"
                    maxlength="500"
                    placeholder="Motivo o detalle del cambio"
                    optional
                    :error="form.errors.nota"
                />

                <InputField
                    id="latitud-estado"
                    v-model="form.latitud"
                    label="Latitud"
                    type="number"
                    step="any"
                    placeholder="13.69890"
                    optional
                    :error="form.errors.latitud"
                />

                <InputField
                    id="longitud-estado"
                    v-model="form.longitud"
                    label="Longitud"
                    type="number"
                    step="any"
                    placeholder="-89.19140"
                    optional
                    :error="form.errors.longitud"
                />

                <div class="sm:col-span-2">
                    <button
                        type="submit"
                        :disabled="
                            form.processing || form.estado === envio.estado
                        "
                        class="inline-flex h-10 items-center rounded-md bg-blue-700 px-4 text-sm font-semibold text-white transition hover:bg-blue-800 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {{
                            form.processing
                                ? 'Guardando...'
                                : 'Registrar cambio'
                        }}
                    </button>
                </div>
            </form>
        </section>

        <section
            class="rounded-xl border border-sidebar-border/70 bg-card p-4 shadow-sm sm:p-5"
        >
            <BiTimeline
                title="Historial de seguimiento"
                :badge="`${eventos.length} eventos`"
                :items="historial"
                empty="No hay eventos registrados para este envío."
            />
        </section>
    </div>
</template>
