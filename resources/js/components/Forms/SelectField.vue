<script setup lang="ts" generic="T extends { id: number }">
import FormField from './FormField.vue';
import { inputClase } from '@/lib/formularios';

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
</script>

<template>
    <FormField
        :id="id"
        :label="label"
        :required="required"
        :error="error"
        :hint="hint"
    >
        <select
            :id="id"
            v-model="model"
            :required="required"
            :aria-invalid="!!error"
            :aria-describedby="error ? `${id}-error` : undefined"
            :class="inputClase"
        >
            <option :value="null" disabled>{{ placeholder }}</option>

            <template v-if="groups">
                <optgroup v-for="g in groups" :key="g.label" :label="g.label">
                    <option v-for="o in g.options" :key="o.id" :value="o.id">
                        {{ etiqueta(o) }}
                    </option>
                </optgroup>
            </template>
            <template v-else>
                <option v-for="o in options" :key="o.id" :value="o.id">
                    {{ etiqueta(o) }}
                </option>
            </template>
        </select>
    </FormField>
</template>
