<script setup lang="ts">
import { Route } from '@/UI/Icons';

defineProps<{ title: string; subtitle?: string }>();
</script>

<template>
    <section
        class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm"
    >
        <div class="border-b border-slate-200 bg-slate-900 px-5 py-4">
            <div class="flex items-center gap-2 text-white">
                <slot name="icon"><Route class="h-5 w-5 text-blue-300" /></slot>
                <h2 class="text-sm font-semibold">{{ title }}</h2>
            </div>
            <p v-if="subtitle" class="mt-1 text-xs text-slate-400">
                {{ subtitle }}
            </p>
        </div>

        <div class="space-y-5 p-5"><slot /></div>
    </section>
</template>
