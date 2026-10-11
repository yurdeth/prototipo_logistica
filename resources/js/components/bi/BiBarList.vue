<script setup lang="ts">
import type { BarRow } from './Utils';

defineProps<{ rows: BarRow[]; active?: string | null; empty?: string }>();
defineEmits<{ select: [key: string] }>();

const fila =
    'flex w-full flex-wrap items-center gap-3 rounded-lg border px-3 py-3 text-left text-sm transition-colors sm:px-4';
const conBarra =
    'w-full rounded-lg border p-3 text-left transition-colors sm:p-4';
const estado = (on: boolean) =>
    on
        ? 'border-sidebar-border bg-muted/70'
        : 'border-transparent hover:border-sidebar-border/70 hover:bg-muted/40';
</script>

<template>
    <ul class="flex flex-col gap-2">
        <li v-for="r in rows" :key="r.key">
            <button
                type="button"
                :class="[
                    r.width === undefined ? fila : conBarra,
                    estado(active === r.key),
                ]"
                :aria-pressed="active === r.key"
                @click="$emit('select', r.key)"
            >
                <!-- Fila simple: indicador, nombre, aviso y total. -->
                <template v-if="r.width === undefined">
                    <span
                        v-if="r.dot"
                        class="h-2.5 w-2.5 shrink-0 rounded-full"
                        :style="{ backgroundColor: r.dot }"
                    />
                    <span class="min-w-24 flex-1 font-medium text-foreground">{{
                        r.label
                    }}</span>
                    <span
                        v-if="r.badge"
                        class="text-xs font-medium"
                        :style="{ color: r.badgeColor }"
                        >{{ r.badge }}</span
                    >
                    <span
                        class="text-right text-xs text-muted-foreground sm:w-24 sm:text-sm"
                        >{{ r.detail }}</span
                    >
                </template>

                <!-- Fila con barra proporcional. -->
                <template v-else>
                    <div
                        class="mb-2 flex flex-col gap-1 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <span
                            class="text-sm font-medium text-foreground"
                            :class="r.mono ? 'font-mono' : ''"
                            >{{ r.label }}</span
                        >
                        <span class="text-xs text-muted-foreground sm:text-sm">
                            {{ r.detail
                            }}<template v-if="r.detail && r.accent">
                                · </template
                            ><span
                                class="font-medium"
                                :style="{ color: r.accentColor }"
                                >{{ r.accent }}</span
                            >
                        </span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-muted">
                        <div
                            class="h-full rounded-full transition-all"
                            :style="{
                                width: `${r.width}%`,
                                backgroundColor: r.color,
                            }"
                        />
                    </div>
                </template>
            </button>
        </li>

        <!-- Mensaje alternativo cuando no hay datos. -->
        <li
            v-if="!rows.length && empty"
            class="rounded-lg border border-dashed border-sidebar-border/70 px-4 py-8 text-center text-sm text-muted-foreground"
        >
            {{ empty }}
        </li>
    </ul>
</template>
