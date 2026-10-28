<script setup lang="ts" generic="T extends string | number">
import { computed, useAttrs } from 'vue';
import FormField from './FormField.vue';
import { inputClase } from '@/lib/formularios.js';

// `class` y `style` van al contenedor (p. ej. sm:col-span-2);
// el resto de atributos (min, step, placeholder, @input...) van al <input>.
defineOptions({ inheritAttrs: false });

const props = defineProps<{
    id: string;
    label: string;
    type?: string;
    required?: boolean;
    optional?: boolean;
    error?: string;
    hint?: string;
    prefix?: string;
    suffix?: string;
}>();

const model = defineModel<T>();
const attrs = useAttrs();

const inputAttrs = computed(() => {
    const { class: _c, style: _s, ...rest } = attrs;
    return rest;
});

// Los negativos solo son válidos si el `min` lo permite (ej. coordenadas);
// con min >= 0 (peso, km, costo…) el signo no debe poder teclearse.
const permiteNegativos = computed(() => {
    const min = Number(attrs.min ?? NaN);

    return Number.isNaN(min) || min < 0;
});

// Tope duro del campo: los atributos max/min de HTML no bloquean el tipeo.
const maximo = computed(() => {
    const max = Number(attrs.max ?? NaN);

    return Number.isNaN(max) ? null : max;
});

// Bloquea tipear signo y notación exponencial ("1e5"); el pegado se cubre en onInput.
const onKeydown = (e: KeyboardEvent) => {
    if (
        props.type === 'number' &&
        !permiteNegativos.value &&
        (e.key === '-' || e.key === '+' || e.key === 'e' || e.key === 'E')
    ) {
        e.preventDefault();
    }
};

const onInput = (e: Event) => {
    const el = e.target as HTMLInputElement;

    if (props.type === 'number') {
        let valor = el.valueAsNumber;
        // "1e5" pasa el valueAsNumber (100000): se descarta si trae exponente.
        const conExponente = /e/i.test(el.value);

        if (maximo.value !== null && valor > maximo.value) {
            valor = maximo.value;
        }

        if (
            Number.isNaN(valor) ||
            conExponente ||
            (!permiteNegativos.value && valor < 0)
        ) {
            el.value = '';
            model.value = '' as T;

            return;
        }

        // Si el tope recortó lo tecleado, sincroniza el DOM a mano: el modelo
        // puede quedar igual que antes y Vue no repintaría el input.
        if (el.valueAsNumber !== valor) {
            el.value = String(valor);
        }

        model.value = valor as T;
    } else {
        model.value = el.value as T;
    }
};
</script>

<template>
    <FormField
        :id="id"
        :label="label"
        :required="required"
        :optional="optional"
        :error="error"
        :hint="hint"
        :class="attrs.class"
        :style="attrs.style as any"
    >
        <div class="relative">
            <span
                v-if="prefix"
                class="pointer-events-none absolute top-2.5 left-3 text-sm text-slate-400"
            >
                {{ prefix }}
            </span>

            <input
                :id="id"
                v-bind="inputAttrs"
                :type="type ?? 'text'"
                :value="model ?? ''"
                :required="required"
                :aria-invalid="!!error"
                :aria-describedby="error ? `${id}-error` : undefined"
                :class="[inputClase, prefix && 'pl-7', suffix && 'pr-10']"
                @keydown="onKeydown"
                @input="onInput"
            />

            <span
                v-if="suffix"
                class="pointer-events-none absolute top-2.5 right-3 text-xs text-slate-400"
            >
                {{ suffix }}
            </span>
        </div>

        <template v-if="$slots.hint" #hint><slot name="hint" /></template>
    </FormField>
</template>
