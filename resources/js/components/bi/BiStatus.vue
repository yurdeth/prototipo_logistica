<script setup lang="ts">
// Estado identificado con un punto de color y texto neutro.
defineProps<{ color: string; label: string }>();
</script>

<template>
    <span class="inline-flex items-center gap-2">
        <span
            class="h-2 w-2 shrink-0 rounded-full"
            :style="{ backgroundColor: color }"
        />
        {{ label }}
    </span>
</template>
