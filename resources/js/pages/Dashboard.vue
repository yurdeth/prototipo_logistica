<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { colors } from '@/UI/Colors';
import { Truck, Route, MapPin, BarChart3 } from '@/UI/Icons';
import { dashboard } from '@/routes';

defineOptions({
    layout: {
        breadcrumbs: [
            {
                title: 'Dashboard',
                href: dashboard(),
            },
        ],
    },
});

// Datos de demostración del prototipo.
const stats = [
    {
        label: 'Envíos activos',
        value: '1,284',
        delta: '+12.4%',
        isGood: true,
        icon: Truck,
    },
    {
        label: 'Rutas en operación',
        value: '42',
        delta: '+3',
        isGood: true,
        icon: Route,
    },
    {
        label: 'Costos operativos',
        value: '$86.4K',
        delta: '-3.2%',
        isGood: true,
        icon: MapPin,
    },
    {
        label: 'Utilización de flota',
        value: '78.4%',
        delta: '-1.8%',
        isGood: false,
        icon: BarChart3,
    },
];

const monthlyShipments = [
    { month: 'Ene', value: 42 },
    { month: 'Feb', value: 55 },
    { month: 'Mar', value: 48 },
    { month: 'Abr', value: 63 },
    { month: 'May', value: 58 },
    { month: 'Jun', value: 71 },
    { month: 'Jul', value: 66 },
    { month: 'Ago', value: 78 },
    { month: 'Sep', value: 72 },
    { month: 'Oct', value: 85 },
];

const maxShipment = Math.max(...monthlyShipments.map((d) => d.value));
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
        <!-- Indicadores clave -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div
                v-for="stat in stats"
                :key="stat.label"
                class="rounded-xl border border-sidebar-border/70 bg-card p-4"
            >
                <div class="flex items-center justify-between">
                    <span class="text-xs text-muted-foreground">
                        {{ stat.label }}
                    </span>

                    <span
                        class="flex h-8 w-8 items-center justify-center rounded-lg text-white"
                        :style="{ backgroundColor: colors.azulClaro }"
                    >
                        <component
                            :is="stat.icon"
                            class="h-4 w-4"
                            aria-hidden="true"
                        />
                    </span>
                </div>

                <p class="mt-2 text-2xl font-semibold text-foreground">
                    {{ stat.value }}
                </p>

                <p
                    class="mt-1 text-xs"
                    :class="
                        stat.isGood
                            ? 'text-green-600 dark:text-green-400'
                            : 'text-red-600 dark:text-red-400'
                    "
                >
                    {{ stat.delta }} vs. mes anterior
                </p>
            </div>
        </div>

        <!-- Desempeño logístico -->
        <div class="grid gap-4 lg:grid-cols-3">
            <div
                class="rounded-xl border border-sidebar-border/70 bg-card p-5 lg:col-span-2"
            >
                <div class="flex items-start justify-between">
                    <div>
                        <span class="text-xs text-muted-foreground">
                            Indicadores de operación
                        </span>

                        <h2 class="mt-1 text-base font-medium text-foreground">
                            Desempeño logístico
                        </h2>
                    </div>

                    <span
                        class="rounded-lg bg-green-50 px-3 py-1 text-sm font-medium text-green-600 dark:bg-green-950 dark:text-green-400"
                    >
                        +12.4%
                    </span>
                </div>

                <!-- Envíos por mes -->
                <div
                    class="mt-6 flex h-40 items-end justify-between gap-2"
                    role="img"
                    aria-label="Envíos por mes, de enero a octubre"
                >
                    <div
                        v-for="(data, i) in monthlyShipments"
                        :key="data.month"
                        class="flex flex-1 flex-col items-center gap-2"
                    >
                        <div
                            class="w-full max-w-6 rounded-t"
                            :style="{
                                height: `${(data.value / maxShipment) * 100}%`,
                                backgroundColor:
                                    i === monthlyShipments.length - 1
                                        ? colors.naranjaClaro
                                        : colors.azulClaro,
                            }"
                            :title="`${data.month}: ${data.value} envíos`"
                        />

                        <span class="text-[10px] text-muted-foreground">
                            {{ data.month }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Flujo de información -->
            <div
                class="flex flex-col rounded-xl p-5 text-white"
                :style="{ backgroundColor: colors.azulOscuro }"
            >
                <span class="text-xs text-slate-300">
                    Flujo de información
                </span>

                <p class="mt-1 text-sm font-medium">
                    Datos → Procesamiento → Indicadores → Análisis
                </p>

                <div class="mt-auto grid gap-3 pt-6">
                    <div
                        v-for="step in [
                            'Registro de datos operacionales',
                            'Integración y transformación',
                            'Indicadores de inteligencia de negocios',
                        ]"
                        :key="step"
                        class="rounded-lg bg-white/10 px-3 py-2 text-xs text-slate-200"
                    >
                        {{ step }}
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
