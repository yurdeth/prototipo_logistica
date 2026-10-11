<script setup lang="ts">
import { toRef } from 'vue';
import { colors } from '@/UI/Colors';
import { CheckCircle2 } from '@/UI/Icons';
import { useChecklist } from '@/composables/useChecklist';
import type { ChecklistItem } from '@/lib/formularios';
import ProgressBar from './ProgressBar.vue';

const props = withDefaults(
    defineProps<{ items: ChecklistItem[]; title?: string }>(),
    { title: 'Requisitos' },
);

const { completados, total, completo } = useChecklist(toRef(props, 'items'));
</script>

<template>
    <div>
        <div class="flex items-center justify-between">
            <p class="text-sm font-semibold text-slate-800">{{ title }}</p>
            <span class="text-xs font-medium text-slate-500"
                >{{ completados }} de {{ total }}</span
            >
        </div>

        <ProgressBar
            class="mt-2"
            size="sm"
            :value="(completados / total) * 100"
            :color="completo ? colors.verde : colors.azulClaro"
        />

        <ul class="mt-4 space-y-3">
            <li
                v-for="c in items"
                :key="c.label"
                class="flex items-center gap-2 text-xs"
                :class="c.ok ? 'text-slate-700' : 'text-slate-400'"
            >
                <CheckCircle2
                    v-if="c.ok"
                    class="h-4 w-4 shrink-0 text-emerald-600"
                />
                <span
                    v-else
                    class="h-4 w-4 shrink-0 rounded-full border border-slate-300"
                />
                {{ c.label }}
            </li>
        </ul>
    </div>
</template>
