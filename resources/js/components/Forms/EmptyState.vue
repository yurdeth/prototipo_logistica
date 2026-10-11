<script setup lang="ts">
// Pensado para usarse dentro de <ul>/<ol>.
defineProps<{ title: string; description?: string }>();
</script>

<template>
    <li
        class="rounded-md border border-dashed border-slate-300 px-4 py-10 text-center"
    >
        <div class="flex justify-center text-slate-300">
            <slot name="icon" />
        </div>
        <p class="mt-2 text-sm font-medium text-slate-600">{{ title }}</p>
        <p
            v-if="description"
            class="mx-auto mt-1 max-w-xs text-xs leading-relaxed text-slate-400"
        >
            {{ description }}
        </p>
    </li>
</template>
