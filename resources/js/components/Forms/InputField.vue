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

const onInput = (e: Event) => {
    const el = e.target as HTMLInputElement;

    if (props.type === 'number') {
        model.value = (
            Number.isNaN(el.valueAsNumber) ? '' : el.valueAsNumber
        ) as T;
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
