<script setup lang="ts">
// Bloque del resumen: el divisor se añade solo si no es el primero.
defineProps<{ label?: string }>();
</script>

<template>
    <div
        class="[&:not(:first-child)]:border-t [&:not(:first-child)]:border-slate-100 [&:not(:first-child)]:pt-4"
    >
        <div
            v-if="label || $slots.side"
            class="flex items-center justify-between gap-3"
        >
            <p
                class="text-xs font-medium tracking-wide text-slate-500 uppercase"
            >
                {{ label }}
            </p>
            <slot name="side" />
        </div>
        <div :class="label || $slots.side ? 'mt-3' : ''"><slot /></div>
    </div>
</template>
