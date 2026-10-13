<script setup lang="ts">
import type { Stat } from './Utils';

// Define cuántas tarjetas se mostrarán por fila.
withDefaults(
    defineProps<{
        items: Stat[];
        cols?: 3 | 4;
        variant?: 'default' | 'dashboard';
    }>(),
    { cols: 3, variant: 'default' },
);
</script>

<template>
    <!-- Indicadores clave adaptables al tamaño de pantalla. -->
    <section
        aria-label="Resumen"
        class="grid grid-cols-1 gap-4 sm:grid-cols-2"
        :class="cols === 4 ? 'xl:grid-cols-4' : 'xl:grid-cols-3'"
    >
        <div
            v-for="s in items"
            :key="s.label"
            class="relative min-w-0 overflow-hidden rounded-2xl border border-sidebar-border/70 bg-card transition-all duration-200"
            :class="
                variant === 'dashboard'
                    ? 'p-4 shadow-sm hover:-translate-y-0.5 hover:shadow-md sm:p-5'
                    : 'rounded-xl p-5 shadow-sm hover:shadow-md'
            "
        >
            <span
                v-if="variant === 'dashboard'"
                class="absolute inset-x-0 top-0 h-1"
                :style="{
                    backgroundColor: s.accentColor ?? 'var(--primary)',
                }"
                aria-hidden="true"
            />

            <div
                class="flex items-center justify-between gap-3"
                :class="variant === 'dashboard' ? 'mt-1' : ''"
            >
                <p
                    class="text-sm font-medium text-muted-foreground"
                    :class="
                        variant === 'dashboard'
                            ? 'text-xs font-semibold tracking-[0.12em] uppercase'
                            : ''
                    "
                >
                    {{ s.label }}
                </p>
                <span
                    v-if="variant === 'dashboard'"
                    class="h-2.5 w-2.5 shrink-0 rounded-full"
                    :style="{
                        backgroundColor: s.accentColor ?? 'var(--primary)',
                    }"
                    aria-hidden="true"
                />
            </div>

            <p
                class="mt-2 font-semibold tracking-tight break-words text-foreground"
                :class="
                    variant === 'dashboard'
                        ? 'text-3xl sm:text-4xl'
                        : 'text-3xl'
                "
                :style="{ color: s.color }"
            >
                {{ s.value }}
            </p>

            <p
                v-if="s.hint"
                class="mt-2 break-words text-muted-foreground"
                :class="
                    variant === 'dashboard'
                        ? 'text-xs leading-relaxed'
                        : 'text-sm'
                "
            >
                {{ s.hint }}
            </p>
        </div>
    </section>
</template>
