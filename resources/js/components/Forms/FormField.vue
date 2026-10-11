<script setup lang="ts">
import { errorClase, labelClase } from '@/lib/formularios';

defineProps<{
    id: string;
    label: string;
    required?: boolean;
    optional?: boolean;
    error?: string;
    hint?: string;
}>();
</script>

<template>
    <div>
        <label :for="id" :class="labelClase">
            {{ label }}
            <span v-if="required" class="text-red-600">*</span>
            <span v-if="optional" class="font-normal text-slate-400"
                >· opcional</span
            >
        </label>

        <slot />

        <p v-if="hint || $slots.hint" class="mt-1.5 text-xs text-slate-500">
            <slot name="hint">{{ hint }}</slot>
        </p>
        <p v-if="error" :id="`${id}-error`" role="alert" :class="errorClase">
            {{ error }}
        </p>
    </div>
</template>
