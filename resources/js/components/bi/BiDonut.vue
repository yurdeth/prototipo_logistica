<script setup lang="ts">
import { computed } from 'vue';

// Gráfico de dona con su leyenda (nombre, cantidad y porcentaje).
const props = defineProps<{
    segments: { label: string; value: number; color: string }[];
    centerLabel?: string;
}>();

const R = 52;
const C = 2 * Math.PI * R;

const total = computed(() => props.segments.reduce((s, x) => s + x.value, 0));

// Cada segmento ocupa una parte proporcional de la circunferencia.
const arcos = computed(() => {
    let acumulado = 0;
    return props.segments.map((s) => {
        const largo = total.value ? (s.value / total.value) * C : 0;
        const arco = { ...s, largo, desfase: -acumulado };
        acumulado += largo;
        return arco;
    });
});
const pct = (v: number) =>
    total.value ? ((v / total.value) * 100).toFixed(1) : '0.0';
</script>

<template>
    <div
        class="flex flex-wrap items-center gap-5 rounded-2xl border border-sidebar-border/70 bg-gradient-to-br from-card to-muted/10 p-4 shadow-sm"
    >
        <div
            class="relative h-40 w-40 shrink-0 sm:h-44 sm:w-44"
            role="img"
            :aria-label="`Distribución de ${total} envíos por estado`"
        >
            <div
                class="absolute inset-2 rounded-full border border-dashed border-sidebar-border/70"
            ></div>
            <svg
                viewBox="0 0 120 120"
                class="h-full w-full -rotate-90 drop-shadow-sm"
            >
                <circle
                    cx="60"
                    cy="60"
                    :r="R"
                    fill="none"
                    stroke-width="16"
                    class="stroke-muted/40"
                />
                <circle
                    v-for="a in arcos"
                    :key="a.label"
                    cx="60"
                    cy="60"
                    :r="R"
                    fill="none"
                    stroke-width="16"
                    stroke-linecap="round"
                    :stroke="a.color"
                    :stroke-dasharray="`${a.largo} ${C - a.largo}`"
                    :stroke-dashoffset="a.desfase"
                />
            </svg>
            <div
                class="absolute inset-0 flex flex-col items-center justify-center"
            >
                <span
                    class="text-2xl font-bold tracking-tight text-foreground sm:text-[1.8rem]"
                    >{{ total }}</span
                >
                <span
                    class="text-[11px] tracking-[0.18em] text-muted-foreground uppercase"
                    >{{ centerLabel ?? 'Total' }}</span
                >
            </div>
        </div>

        <ul class="flex min-w-0 flex-1 flex-col gap-2.5 text-sm">
            <li
                v-for="s in segments"
                :key="s.label"
                class="flex items-center gap-2 rounded-lg border border-transparent bg-muted/20 px-2 py-1.5 hover:border-sidebar-border/70"
            >
                <span
                    class="h-2.5 w-2.5 shrink-0 rounded-full ring-4 ring-white/60"
                    :style="{ backgroundColor: s.color }"
                />
                <span class="flex-1 font-medium text-foreground">{{
                    s.label
                }}</span>
                <span class="font-semibold text-foreground tabular-nums">{{
                    s.value
                }}</span>
                <span class="w-12 text-right text-muted-foreground"
                    >{{ pct(s.value) }}%</span
                >
            </li>
        </ul>
    </div>
</template>
