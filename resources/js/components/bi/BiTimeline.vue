<script setup lang="ts">
import type { TimelineItem } from './Utils';

defineProps<{
    title: string;
    items: TimelineItem[];
    badge?: string;
    empty?: string;
}>();
</script>

<template>
    <!-- Línea de tiempo con los eventos o paradas del elemento. -->
    <div class="mt-8">
        <div class="flex items-center justify-between">
            <h3 class="text-sm font-semibold text-foreground">{{ title }}</h3>
            <span
                v-if="badge"
                class="rounded-md bg-muted px-2 py-1 text-xs text-muted-foreground"
                >{{ badge }}</span
            >
        </div>

        <ol v-if="items.length" class="mt-4">
            <li v-for="(it, i) in items" :key="it.key" class="flex gap-3">
                <!-- Indicador y línea que conecta con el siguiente elemento. -->
                <div class="flex flex-col items-center">
                    <span
                        v-if="it.marker !== undefined"
                        class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-medium text-white ring-4 ring-card"
                        :style="{ backgroundColor: it.color }"
                    >
                        {{ it.marker }}
                    </span>
                    <span
                        v-else
                        class="mt-1 h-3 w-3 shrink-0 rounded-full ring-4 ring-card"
                        :style="{ backgroundColor: it.color }"
                    />
                    <span
                        v-if="i < items.length - 1"
                        class="my-1 w-px flex-1 bg-border"
                    />
                </div>

                <div
                    class="mb-5 min-w-0 flex-1 rounded-lg border border-sidebar-border/70 p-3"
                >
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-sm font-semibold text-foreground">
                            {{ it.title }}
                        </p>
                        <span
                            v-if="it.tag"
                            class="text-xs font-medium"
                            :style="{ color: it.tagColor }"
                            >{{ it.tag }}</span
                        >
                    </div>
                    <p
                        v-if="it.meta"
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        {{ it.meta }}
                    </p>
                    <p
                        v-if="it.note"
                        class="mt-2 text-sm leading-relaxed text-muted-foreground"
                    >
                        {{ it.note }}
                    </p>
                </div>
            </li>
        </ol>

        <p
            v-else
            class="mt-4 rounded-lg border border-dashed border-sidebar-border/70 px-4 py-6 text-center text-sm text-muted-foreground"
        >
            {{ empty }}
        </p>
    </div>
</template>
