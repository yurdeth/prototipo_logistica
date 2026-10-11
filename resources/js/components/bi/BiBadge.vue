<script setup lang="ts">
import { soft } from './Utils';

// sm: para tablas. md: para el panel de detalle.
withDefaults(
    defineProps<{ color: string; label: string; size?: 'sm' | 'md' }>(),
    { size: 'sm' },
);
</script>

<template>
    <span
        class="inline-flex text-xs font-semibold whitespace-nowrap"
        :class="
            size === 'sm' ? 'rounded-md px-2.5 py-1' : 'rounded-lg px-3 py-2'
        "
        :style="{ color, backgroundColor: soft(color) }"
    >
        {{ label }}
    </span>
</template>
