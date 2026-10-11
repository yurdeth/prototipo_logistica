<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { colors } from '@/UI/Colors';
import { Download, Plus } from '@/UI/Icons';

// `href` es la ruta del formulario de creación.
defineProps<{
    title: string;
    subtitle: string;
    accion: string;
    href: string;
}>();
defineEmits<{ exportar: [] }>();
</script>

<template>
    <!-- Encabezado de la vista con título y acciones principales. -->
    <header class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-foreground">
                {{ title }}
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">{{ subtitle }}</p>
        </div>

        <div class="flex gap-2">
            <!-- Exporta los datos filtrados en formato CSV. -->
            <button
                type="button"
                class="inline-flex h-10 items-center gap-2 rounded-lg border border-sidebar-border/70 bg-card px-4 text-sm font-medium text-foreground hover:bg-muted"
                @click="$emit('exportar')"
            >
                <Download class="h-4 w-4" aria-hidden="true" />
                Exportar
            </button>

            <!-- Navega al formulario de creación. -->
            <Link
                :href="href"
                class="inline-flex h-10 items-center gap-2 rounded-lg px-4 text-sm font-medium text-white transition-opacity hover:opacity-90"
                :style="{ backgroundColor: colors.azulClaro }"
            >
                <Plus class="h-4 w-4" aria-hidden="true" />
                {{ accion }}
            </Link>
        </div>
    </header>
</template>
