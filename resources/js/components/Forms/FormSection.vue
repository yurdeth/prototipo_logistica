<script setup lang="ts">
// Tarjeta de sección con encabezado (icono o número de paso).
// `flush` elimina el padding del cuerpo para layouts propios.
defineProps<{
    title: string;
    description?: string;
    step?: number;
    flush?: boolean;
}>();
</script>

<template>
    <section
        class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm"
    >
        <header
            class="flex flex-col justify-between gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:px-6"
        >
            <div class="flex items-start gap-3">
                <div
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md bg-blue-50 text-blue-700"
                >
                    <slot name="icon">
                        <span v-if="step" class="text-sm font-semibold">{{
                            step
                        }}</span>
                    </slot>
                </div>
                <div>
                    <h2 class="text-base font-semibold text-slate-900">
                        {{ title }}
                    </h2>
                    <p v-if="description" class="mt-0.5 text-sm text-slate-500">
                        {{ description }}
                    </p>
                </div>
            </div>
            <slot name="actions" />
        </header>

        <div :class="flush ? '' : 'p-5 sm:p-6'">
            <slot />
        </div>

        <footer
            v-if="$slots.footer"
            class="border-t border-slate-100 bg-slate-50 px-5 py-3 sm:px-6"
        >
            <slot name="footer" />
        </footer>
    </section>
</template>
