<script setup lang="ts">
defineProps<{ items: { label: string; color: string }[] }>();
</script>

<template>
    <!-- Leyenda para identificar los colores de una gráfica. -->
    <div
        class="flex flex-wrap items-center gap-4 text-xs text-muted-foreground"
    >
        <span v-for="i in items" :key="i.label" class="flex items-center gap-2">
            <span
                class="h-2.5 w-2.5 rounded-sm"
                :style="{ backgroundColor: i.color }"
            />
            {{ i.label }}
        </span>
    </div>
</template>
