<script setup lang="ts">
import { AlertTriangle } from '@/UI/Icons';
import type { Aviso } from '@/lib/formularios';

withDefaults(defineProps<{ avisos: Aviso[]; title?: string }>(), {
    title: 'Observaciones operativas',
});
</script>

<template>
    <section
        v-if="avisos.length"
        class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm"
    >
        <div
            class="flex items-center gap-2 border-b border-slate-200 px-4 py-3"
        >
            <AlertTriangle class="h-4 w-4 text-amber-600" />
            <h3 class="text-sm font-semibold text-slate-800">{{ title }}</h3>
        </div>

        <ul class="divide-y divide-slate-100">
            <li
                v-for="a in avisos"
                :key="a.texto"
                class="flex items-start gap-2.5 px-4 py-3"
            >
                <AlertTriangle
                    class="mt-0.5 h-4 w-4 shrink-0"
                    :class="a.grave ? 'text-red-600' : 'text-amber-600'"
                />
                <p
                    class="text-xs leading-relaxed"
                    :class="a.grave ? 'text-red-700' : 'text-slate-600'"
                >
                    {{ a.texto }}
                </p>
            </li>
        </ul>
    </section>
</template>
