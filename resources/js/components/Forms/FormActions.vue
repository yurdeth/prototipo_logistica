<script setup lang="ts">
import { Link } from '@inertiajs/vue3';

defineProps<{
    submitLabel: string;
    processingLabel: string;
    processing?: boolean;
    disabled?: boolean;
    cancelHref: string;
    warning?: string | null;
    hint?: string;
}>();
</script>

<template>
    <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
        <button
            type="submit"
            class="inline-flex h-11 w-full items-center justify-center gap-2 rounded-md bg-blue-700 px-4 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-800 focus-visible:ring-2 focus-visible:ring-blue-600 focus-visible:ring-offset-2 focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="disabled || processing"
        >
            <slot name="icon" />
            {{ processing ? processingLabel : submitLabel }}
        </button>

        <Link
            :href="cancelHref"
            class="mt-2 inline-flex h-10 w-full items-center justify-center rounded-md border border-slate-300 bg-white px-4 text-sm font-medium text-slate-700 transition hover:bg-slate-50"
        >
            Cancelar
        </Link>

        <p v-if="warning" class="mt-3 text-xs leading-relaxed text-red-600">
            {{ warning }}
        </p>
        <p
            v-else-if="hint"
            class="mt-3 text-center text-xs leading-relaxed text-slate-400"
        >
            {{ hint }}
        </p>
    </section>
</template>
