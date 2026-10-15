<script setup lang="ts">
import { computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { colors } from '@/UI/Colors';
import { Plus } from '@/UI/Icons';
import type { Opcion } from '@/config/Envios/envioForm';
import { soft } from '@/lib/formularios';
import FormSection from '@/components/Forms/FormSection.vue';
import InputField from '@/components/Forms/InputField.vue';
import SearchSelectField from '@/components/Forms/SearchSelectField.vue';
import FormActions from '@/components/Forms/FormActions.vue';

type Tipo = 'PERSONA' | 'EMPRESA';

interface ClienteEdicion {
    id_cliente: number;
    nombre: string;
    tipo_cliente: Tipo;
    contacto: string | null;
    territorio_id: number;
}

// Formulario compartido por "Registrar cliente" y "Editar cliente".
const props = defineProps<{
    territorios: Opcion[];
    cliente?: ClienteEdicion;
    totalEnvios?: number;
}>();

const editando = computed(() => !!props.cliente);

const form = useForm({
    nombre: props.cliente?.nombre ?? '',
    tipo_cliente: (props.cliente?.tipo_cliente ?? 'PERSONA') as Tipo,
    contacto: props.cliente?.contacto ?? '',
    territorio_id: (props.cliente?.territorio_id ?? null) as number | null,
});

const tipos: { key: Tipo; label: string; hint: string }[] = [
    { key: 'PERSONA', label: 'Persona', hint: 'Cliente individual' },
    { key: 'EMPRESA', label: 'Empresa', hint: 'Negocio u organización' },
];

const completo = computed(
    () => form.nombre.trim().length >= 3 && form.territorio_id !== null,
);

const guardar = () => {
    if (!completo.value || form.processing) return;

    if (props.cliente) {
        form.put(`/clientes/${props.cliente.id_cliente}`, {
            preserveScroll: true,
        });
    } else {
        form.post('/clientes', { preserveScroll: true });
    }
};
</script>

<template>
    <form
        class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_350px]"
        @submit.prevent="guardar"
    >
        <div class="min-w-0 space-y-6">
            <FormSection
                :step="1"
                title="Datos del cliente"
                description="Cómo se identifica el cliente en los envíos."
            >
                <div class="grid gap-5 sm:grid-cols-2">
                    <InputField
                        id="nombre"
                        v-model="form.nombre"
                        class="sm:col-span-2"
                        label="Nombre"
                        maxlength="150"
                        placeholder="Nombre de la persona o la empresa"
                        required
                        :error="form.errors.nombre"
                    />

                    <fieldset class="sm:col-span-2">
                        <legend
                            class="mb-1.5 block text-sm font-medium text-slate-700"
                        >
                            Tipo de cliente <span class="text-red-600">*</span>
                        </legend>

                        <div class="grid grid-cols-2 gap-2">
                            <button
                                v-for="t in tipos"
                                :key="t.key"
                                type="button"
                                :aria-pressed="form.tipo_cliente === t.key"
                                class="rounded-md border px-3 py-2.5 text-sm font-medium shadow-sm transition-colors focus-visible:ring-2 focus-visible:ring-blue-600/30 focus-visible:outline-none"
                                :class="
                                    form.tipo_cliente === t.key
                                        ? ''
                                        : 'border-slate-300 bg-white text-slate-600 hover:bg-slate-50'
                                "
                                :style="
                                    form.tipo_cliente === t.key
                                        ? {
                                              color: colors.azulClaro,
                                              borderColor: colors.azulClaro,
                                              backgroundColor: soft(
                                                  colors.azulClaro,
                                              ),
                                          }
                                        : undefined
                                "
                                @click="form.tipo_cliente = t.key"
                            >
                                {{ t.label }}
                                <span
                                    class="block text-[11px] font-normal opacity-80"
                                    >{{ t.hint }}</span
                                >
                            </button>
                        </div>
                        <p
                            v-if="form.errors.tipo_cliente"
                            class="mt-1 text-xs text-red-600"
                        >
                            {{ form.errors.tipo_cliente }}
                        </p>
                    </fieldset>
                </div>
            </FormSection>

            <FormSection
                :step="2"
                title="Contacto y ubicación"
                description="Cómo comunicarse con el cliente y dónde se ubica."
            >
                <div class="grid gap-5 sm:grid-cols-2">
                    <InputField
                        id="contacto"
                        v-model="form.contacto"
                        label="Contacto"
                        maxlength="150"
                        placeholder="Teléfono o correo electrónico"
                        optional
                        :error="form.errors.contacto"
                    />
                    <SearchSelectField
                        id="territorio"
                        v-model="form.territorio_id"
                        label="Territorio"
                        placeholder="Busca o selecciona un territorio"
                        :options="territorios"
                        required
                        :error="form.errors.territorio_id"
                    />
                </div>
            </FormSection>
        </div>

        <aside class="min-w-0 space-y-4 xl:sticky xl:top-6">
            <div
                v-if="editando"
                class="rounded-lg border border-slate-200 bg-white p-4 text-sm shadow-sm"
            >
                <p class="font-medium text-slate-900">Envíos asociados</p>
                <p class="mt-1 text-2xl font-semibold text-slate-900">
                    {{ totalEnvios ?? 0 }}
                </p>
                <p class="mt-1 text-xs text-slate-500">
                    {{
                        (totalEnvios ?? 0) > 0
                            ? 'Mientras tenga envíos, este cliente no se puede eliminar.'
                            : 'Sin envíos: se puede eliminar desde el listado.'
                    }}
                </p>
            </div>

            <FormActions
                :submit-label="
                    editando ? 'Guardar cambios' : 'Registrar cliente'
                "
                :processing-label="
                    editando ? 'Guardando cambios...' : 'Registrando cliente...'
                "
                :processing="form.processing"
                :disabled="!completo"
                cancel-href="/clientes"
                hint="Verifica el nombre y el territorio antes de guardar."
            >
                <template #icon><Plus class="h-4 w-4" /></template>
            </FormActions>
        </aside>
    </form>
</template>
