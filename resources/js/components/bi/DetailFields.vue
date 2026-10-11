<script setup lang="ts">
import type { Field } from './Utils';

// `responsive`: una columna en móvil y dos en pantallas más grandes.
defineProps<{ title: string; fields: Field[]; responsive?: boolean }>();

const peso = { medium: 'font-medium', semibold: 'font-semibold' };
</script>

<template>
    <!-- Grupo de datos del detalle, cada uno en su propia caja. -->
    <div class="mt-6">
        <h3 class="text-sm font-semibold text-foreground">{{ title }}</h3>
        <dl
            class="mt-3 grid gap-3"
            :class="responsive ? 'grid-cols-1 sm:grid-cols-2' : 'grid-cols-2'"
        >
            <div
                v-for="f in fields"
                :key="f.label"
                class="rounded-lg border border-sidebar-border/70 p-3"
                :class="f.wide ? 'col-span-2' : ''"
            >
                <dt class="text-xs text-muted-foreground">{{ f.label }}</dt>
                <dd
                    class="mt-1 text-sm break-words text-foreground"
                    :class="[
                        f.weight ? peso[f.weight] : '',
                        f.wide ? 'leading-relaxed' : '',
                    ]"
                >
                    {{ f.value }}
                </dd>
            </div>
        </dl>
    </div>
</template>
