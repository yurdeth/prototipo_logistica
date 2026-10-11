<script setup lang="ts">
// Título de cada pestaña de análisis. El slot sirve para la leyenda de las gráficas.
defineProps<{ title: string; description?: string }>();
</script>

<template>
    <div
        class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between"
        :class="description ? 'mb-4' : 'mb-5'"
    >
        <div v-if="description">
            <h3 class="text-sm font-semibold text-foreground">{{ title }}</h3>
            <p class="mt-1 text-sm text-muted-foreground">{{ description }}</p>
        </div>
        <p v-else class="text-sm font-medium text-foreground">{{ title }}</p>

        <slot />
    </div>
</template>
