<script setup lang="ts">
// Caja superior del detalle: agrupa estado y alertas.
withDefaults(defineProps<{ label?: string }>(), { label: 'Estado operativo' });
</script>

<template>
    <div class="rounded-xl border border-sidebar-border/70 bg-muted/20 p-4">
        <p
            class="mb-3 text-xs font-semibold tracking-wide text-muted-foreground uppercase"
        >
            {{ label }}
        </p>
        <div class="flex flex-wrap items-center gap-2"><slot /></div>
    </div>
</template>
