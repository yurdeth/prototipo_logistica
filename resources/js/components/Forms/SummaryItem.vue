<script setup lang="ts">
// Debe usarse dentro de un <dl>.
withDefaults(
    defineProps<{ label: string; size?: 'md' | 'lg'; valueClass?: string }>(),
    { size: 'md' },
);
</script>

<template>
    <div>
        <dt class="text-xs text-slate-500">{{ label }}</dt>
        <dd
            class="mt-1"
            :class="[
                size === 'lg' ? 'text-xl font-semibold' : 'text-sm font-medium',
                valueClass ?? 'text-slate-900',
            ]"
        >
            <slot />
        </dd>
    </div>
</template>
