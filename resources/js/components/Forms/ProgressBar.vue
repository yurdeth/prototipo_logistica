<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{ value: number; color?: string; size?: 'sm' | 'md' }>(),
    { color: '#1d4ed8', size: 'md' },
);

const ancho = computed(() => `${Math.min(Math.max(props.value, 0), 100)}%`);
</script>

<template>
    <div
        class="overflow-hidden rounded-full bg-slate-100"
        :class="size === 'sm' ? 'h-1.5' : 'h-2'"
        role="progressbar"
        aria-valuemin="0"
        aria-valuemax="100"
        :aria-valuenow="Math.round(value)"
    >
        <div
            class="h-full rounded-full transition-all"
            :style="{ width: ancho, backgroundColor: color }"
        />
    </div>
</template>
