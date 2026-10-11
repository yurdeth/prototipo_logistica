<script setup lang="ts">
import { soft } from '@/lib/formularios';

defineProps<{ label: string; color: string }>();
</script>

<template>
    <span
        class="rounded px-1.5 py-0.5 text-[10px] font-semibold"
        :style="{ color, backgroundColor: soft(color) }"
    >
        {{ label }}
    </span>
</template>
