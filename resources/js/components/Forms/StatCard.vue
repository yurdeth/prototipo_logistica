<script setup lang="ts">
defineProps<{
    label: string;
    value?: string | number;
    unit?: string;
    hint?: string;
    dot?: 'ok' | 'pending';
}>();
</script>

<template>
    <div class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <p class="text-xs font-medium tracking-wide text-slate-500 uppercase">
            {{ label }}
        </p>
        <div
            class="mt-2 flex items-center gap-2 text-2xl font-semibold text-slate-900"
        >
            <span
                v-if="dot"
                class="h-2.5 w-2.5 rounded-full"
                :class="dot === 'ok' ? 'bg-emerald-500' : 'bg-amber-500'"
            />
            <slot>
                <span>
                    {{ value }}
                    <span
                        v-if="unit"
                        class="text-sm font-medium text-slate-500"
                        >{{ unit }}</span
                    >
                </span>
            </slot>
        </div>
        <p v-if="hint" class="mt-1 text-xs text-slate-500">{{ hint }}</p>
    </div>
</template>
