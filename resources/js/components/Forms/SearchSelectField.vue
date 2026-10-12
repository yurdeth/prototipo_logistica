<script setup lang="ts" generic="T extends { id: number }">
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue';
import FormField from './FormField.vue';
import { inputClase } from '@/lib/formularios';

// Igual que SelectField, pero se puede escribir para buscar entre las opciones.
const props = defineProps<{
    id: string;
    label: string;
    placeholder: string;
    options?: T[];
    groups?: { label: string; options: T[] }[];
    optionLabel?: (o: T) => string;
    required?: boolean;
    error?: string;
    hint?: string;
}>();

const model = defineModel<number | null>();

const etiqueta = (o: T) =>
    props.optionLabel
        ? props.optionLabel(o)
        : String((o as { nombre?: string }).nombre ?? o.id);

const abierto = ref(false);
const consulta = ref('');
const activo = ref(0);

// La lista se dibuja en <body> con posición fija: así ningún contenedor del formulario
// (overflow, recortes, orden de apilamiento) puede taparla o cortarla.
const entrada = ref<HTMLInputElement | null>(null);
const caja = reactive({ left: 0, width: 0, top: 0, bottom: 0, arriba: false });

const posicionar = () => {
    const el = entrada.value;
    if (!el) return;

    const r = el.getBoundingClientRect();
    const abajo = window.innerHeight - r.bottom;

    caja.left = r.left;
    caja.width = r.width;
    caja.top = r.bottom + 4;
    caja.bottom = window.innerHeight - r.top + 4;
    // Si abajo no hay espacio para la lista, se abre hacia arriba
    caja.arriba = abajo < 260 && r.top > abajo;
};

const estilo = computed(() => ({
    position: 'fixed' as const,
    left: `${caja.left}px`,
    width: `${caja.width}px`,
    ...(caja.arriba ? { bottom: `${caja.bottom}px` } : { top: `${caja.top}px` }),
}));

// Mientras está abierta, la lista sigue al campo si se hace scroll o cambia el tamaño.
watch(
    abierto,
    (v) => {
        if (v) {
            posicionar();
            window.addEventListener('scroll', posicionar, true);
            window.addEventListener('resize', posicionar);
        } else {
            window.removeEventListener('scroll', posicionar, true);
            window.removeEventListener('resize', posicionar);
        }
    },
    { flush: 'sync' },
);

onBeforeUnmount(() => {
    window.removeEventListener('scroll', posicionar, true);
    window.removeEventListener('resize', posicionar);
});

// Busca sin distinguir mayúsculas ni acentos ("maria" encuentra "María").
const normalizar = (t: string) =>
    t.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

const grupos = computed(
    () => props.groups ?? [{ label: '', options: props.options ?? [] }],
);

const filtrados = computed(() => {
    const q = normalizar(consulta.value.trim());

    return grupos.value
        .map((g) => ({
            label: g.label,
            options: q
                ? g.options.filter((o) => normalizar(etiqueta(o)).includes(q))
                : g.options,
        }))
        .filter((g) => g.options.length > 0);
});

const planos = computed(() => filtrados.value.flatMap((g) => g.options));

const seleccionado = computed(
    () =>
        grupos.value
            .flatMap((g) => g.options)
            .find((o) => o.id === model.value) ?? null,
);

// Cerrado muestra la opción elegida; abierto muestra lo que se está escribiendo.
const texto = computed(() =>
    abierto.value
        ? consulta.value
        : seleccionado.value
          ? etiqueta(seleccionado.value)
          : '',
);

const idOpcion = (o: T) => `${props.id}-opcion-${o.id}`;

const abrir = () => {
    consulta.value = '';
    activo.value = Math.max(
        0,
        planos.value.findIndex((o) => o.id === model.value),
    );
    abierto.value = true;
};

const cerrar = () => {
    abierto.value = false;
    consulta.value = '';
};

const alEscribir = (e: Event) => {
    consulta.value = (e.target as HTMLInputElement).value;
    abierto.value = true;
    activo.value = 0;
};

const elegir = (o: T) => {
    model.value = o.id;
    cerrar();
};

const alTecla = (e: KeyboardEvent) => {
    if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (!abierto.value) return abrir();
        activo.value = Math.min(activo.value + 1, planos.value.length - 1);
    } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        activo.value = Math.max(activo.value - 1, 0);
    } else if (e.key === 'Enter' && abierto.value) {
        e.preventDefault(); // no enviar el formulario al elegir
        const o = planos.value[activo.value];
        if (o) elegir(o);
    } else if (e.key === 'Escape' && abierto.value) {
        e.preventDefault();
        cerrar();
    }
};

// Mantiene visible la opción resaltada al navegar con el teclado.
watch(activo, async () => {
    await nextTick();
    const o = planos.value[activo.value];
    if (o) document.getElementById(idOpcion(o))?.scrollIntoView({ block: 'nearest' });
});
</script>

<template>
    <FormField
        :id="id"
        :label="label"
        :required="required"
        :error="error"
        :hint="hint"
    >
        <div class="relative">
            <input
                :id="id"
                ref="entrada"
                type="text"
                role="combobox"
                autocomplete="off"
                :value="texto"
                :placeholder="placeholder"
                :aria-required="required"
                :aria-invalid="!!error"
                :aria-describedby="error ? `${id}-error` : undefined"
                :aria-expanded="abierto"
                :aria-controls="`${id}-lista`"
                :aria-activedescendant="
                    abierto && planos[activo] ? idOpcion(planos[activo]) : undefined
                "
                aria-autocomplete="list"
                :class="[inputClase, 'pr-9']"
                @focus="abrir"
                @blur="cerrar"
                @input="alEscribir"
                @keydown="alTecla"
            />

            <svg
                class="pointer-events-none absolute top-1/2 right-3 h-4 w-4 -translate-y-1/2 text-slate-400"
                viewBox="0 0 20 20"
                fill="none"
                stroke="currentColor"
                stroke-width="1.5"
                aria-hidden="true"
            >
                <path d="m6 8 4 4 4-4" stroke-linecap="round" stroke-linejoin="round" />
            </svg>

            <Teleport to="body">
            <ul
                v-show="abierto"
                :id="`${id}-lista`"
                role="listbox"
                :style="estilo"
                class="z-[60] max-h-60 overflow-auto rounded-md border border-slate-200 bg-white py-1 shadow-lg"
            >
                <template v-for="g in filtrados" :key="g.label">
                    <li
                        v-if="g.label"
                        role="presentation"
                        class="px-3 pt-2 pb-1 text-xs font-semibold tracking-wide text-slate-500 uppercase"
                    >
                        {{ g.label }}
                    </li>

                    <li
                        v-for="o in g.options"
                        :id="idOpcion(o)"
                        :key="o.id"
                        role="option"
                        :aria-selected="o.id === model"
                        class="cursor-pointer px-3 py-2 text-sm text-slate-700"
                        :class="[
                            planos[activo]?.id === o.id ? 'bg-blue-50' : '',
                            o.id === model ? 'font-medium text-slate-900' : '',
                        ]"
                        @mousedown.prevent="elegir(o)"
                        @mouseenter="activo = planos.findIndex((p) => p.id === o.id)"
                    >
                        {{ etiqueta(o) }}
                    </li>
                </template>

                <li
                    v-if="planos.length === 0"
                    class="px-3 py-2 text-sm text-slate-500"
                >
                    Sin resultados para "{{ consulta }}".
                </li>
            </ul>
            </Teleport>
        </div>
    </FormField>
</template>