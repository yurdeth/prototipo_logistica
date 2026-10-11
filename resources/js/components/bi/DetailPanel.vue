<script setup lang="ts">
import { X } from '@/UI/Icons';

// `tipo` es el texto pequeño superior (ej. "Detalle del envío"); `title` es el código.
defineProps<{ tipo: string; title: string }>();
defineEmits<{ close: [] }>();
</script>

<template>
    <!-- Panel lateral con la información detallada del elemento seleccionado. -->
    <div
        class="fixed inset-0 z-50 flex justify-end bg-black/40 backdrop-blur-[2px]"
        @click.self="$emit('close')"
    >
        <aside
            class="flex h-full w-full max-w-md flex-col overflow-y-auto border-l border-sidebar-border/70 bg-card shadow-xl"
            :aria-label="tipo"
        >
            <div
                class="flex items-start justify-between border-b border-sidebar-border/70 px-6 py-5"
            >
                <div>
                    <p
                        class="text-xs font-semibold tracking-wider text-muted-foreground uppercase"
                    >
                        {{ tipo }}
                    </p>
                    <h2
                        class="mt-1 font-mono text-xl font-semibold tracking-tight text-foreground"
                    >
                        {{ title }}
                    </h2>
                </div>

                <button
                    type="button"
                    class="rounded-lg border border-sidebar-border/70 p-2 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    aria-label="Cerrar detalle"
                    @click="$emit('close')"
                >
                    <X class="h-4 w-4" />
                </button>
            </div>

            <div class="flex-1 p-6"><slot /></div>
        </aside>
    </div>
</template>
