<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { colors } from '@/UI/Colors';
import { Truck } from '@/UI/Icons';
import { loginContent } from '@/config/infoLogin';
import { home } from '@/routes';

const page = usePage();
const name = page.props.name;

defineProps<{
    title?: string;
    description?: string;
}>();

// Anchos (px) de las barras del código de barras decorativo
const barcode: number[] = [
    2, 4, 1, 3, 1, 5, 2, 1, 4, 2, 1, 3, 5, 1, 2, 4, 1, 3, 2, 4, 1, 5, 2, 1,
];
</script>

<template>
    <div class="grid min-h-svh lg:grid-cols-2">
        <!-- ====================================================== -->
        <!-- PANEL IZQUIERDO -->
        <div
            class="relative hidden flex-col justify-between overflow-hidden p-10 text-white lg:flex"
            :style="{ backgroundColor: colors.azulOscuro }"
        >
            <!-- Decoración -->
            <div
                class="pointer-events-none absolute -bottom-40 -left-40 h-96 w-96 rounded-full border opacity-5"
                :style="{ borderColor: colors.naranjaClaro }"
            />

            <!-- LOGO -->
            <Link
                :href="home()"
                class="relative z-10 flex items-center gap-2 text-base font-medium"
            >
                <span
                    class="flex h-9 w-9 items-center justify-center rounded-lg"
                    :style="{ backgroundColor: colors.naranjaClaro }"
                >
                    <Truck class="h-5 w-5 text-white" aria-hidden="true" />
                </span>
                {{ name }}
            </Link>

            <!-- CONTENIDO CENTRAL -->
            <div class="relative z-10 flex flex-col">
                <!-- Mensaje principal -->
                <div class="max-w-md">
                    <span
                        class="inline-block rounded-full border px-3 py-1 text-xs font-medium text-orange-300"
                        :style="{ borderColor: colors.naranjaClaro }"
                    >
                        {{ loginContent.badge }}
                    </span>

                    <h2
                        class="mt-4 text-2xl leading-tight font-semibold md:text-3xl"
                    >
                        {{ loginContent.title }}
                    </h2>

                    <p class="mt-3 text-sm leading-relaxed text-slate-300">
                        {{ loginContent.description }}
                    </p>
                </div>

                <!-- CARD DE ANALÍTICA                                  -->
                <div
                    class="mt-5 rounded-xl border border-white/20 bg-white/5 p-4 backdrop-blur-sm"
                    aria-hidden="true"
                >
                    <!-- Título -->
                    <div class="flex items-center justify-between">
                        <span
                            class="font-mono text-[11px] font-medium tracking-wider"
                            :style="{ color: colors.grisClaro }"
                        >
                            {{ loginContent.insightTitle }}
                        </span>

                        <span
                            class="rounded-full border px-2 py-0.5 text-[9px] tracking-wider uppercase"
                            :style="{
                                borderColor: `${colors.naranjaClaro}60`,
                                color: colors.naranjaClaro,
                            }"
                        >
                            BI
                        </span>
                    </div>

                    <!-- VISUALIZACIÓN DE RUTAS -->
                    <svg viewBox="0 0 320 110" class="mt-2 block w-full">
                        <!-- Ruta 1 -->
                        <path
                            d="M22 88 C 50 40, 85 36, 108 52"
                            fill="none"
                            :stroke="colors.azulClaro"
                            stroke-width="1.5"
                            stroke-dasharray="4 4"
                        />

                        <!-- Ruta 2 -->
                        <path
                            d="M108 52 C 135 100, 175 100, 200 76"
                            fill="none"
                            :stroke="colors.azulClaro"
                            stroke-width="1.5"
                            stroke-dasharray="4 4"
                        />

                        <!-- Ruta principal -->
                        <path
                            d="M200 76 C 228 28, 258 20, 296 28"
                            fill="none"
                            :stroke="colors.naranjaClaro"
                            stroke-width="2"
                        />

                        <!-- Ruta secundaria -->
                        <path
                            d="M108 52 C 145 14, 190 10, 232 16"
                            fill="none"
                            :stroke="colors.azulClaro"
                            stroke-width="1"
                            stroke-dasharray="2 5"
                            opacity="0.5"
                        />

                        <!-- Puntos -->
                        <circle
                            cx="22"
                            cy="88"
                            r="4.5"
                            :fill="colors.azulClaro"
                        />

                        <circle
                            cx="108"
                            cy="52"
                            r="4.5"
                            :fill="colors.azulClaro"
                        />

                        <circle
                            cx="200"
                            cy="76"
                            r="4.5"
                            :fill="colors.azulClaro"
                        />

                        <circle
                            cx="232"
                            cy="16"
                            r="3"
                            :fill="colors.grisMedio"
                        />

                        <!-- Destino -->
                        <circle
                            cx="296"
                            cy="28"
                            r="6"
                            :fill="colors.naranjaClaro"
                        />

                        <circle
                            cx="296"
                            cy="28"
                            r="11"
                            fill="none"
                            :stroke="colors.naranjaClaro"
                            stroke-opacity="0.4"
                            class="motion-safe:animate-pulse"
                        />

                        <!-- Etiquetas -->
                        <g
                            font-size="9"
                            :fill="colors.grisClaro"
                            font-family="ui-monospace, monospace"
                        >
                            <text x="10" y="105">A</text>
                            <text x="97" y="42">B</text>
                            <text x="188" y="94">C</text>
                            <text x="280" y="50">D</text>
                            <text x="230" y="10">E</text>
                        </g>
                    </svg>

                    <!-- MÉTRICAS-->
                    <div class="mt-3 grid grid-cols-3 gap-2">
                        <div
                            v-for="metric in loginContent.metrics"
                            :key="metric.value"
                            class="rounded-lg border border-white/5 bg-white/5 px-3 py-2.5"
                        >
                            <p class="text-xs font-semibold text-white">
                                {{ metric.value }}
                            </p>

                            <p
                                class="mt-1 text-[9px] leading-tight text-slate-400"
                            >
                                {{ metric.label }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FOOTER                                             -->
            <div class="relative z-10">
                <p class="text-[10px] text-slate-500">
                    Analítica · Indicadores · Inteligencia de Negocios
                </p>
            </div>
        </div>

        <!-- PANEL DERECHO / LOGIN  -->

        <main
            class="flex items-center justify-center px-4 py-10 lg:bg-slate-100 lg:px-10"
        >
            <div
                class="waybill relative w-full max-w-sm rounded-md p-5 font-mono shadow-xl"
                :style="{
                    backgroundColor: colors.papel,
                    color: colors.azulOscuro,
                }"
            >
                <!-- ENCABEZADO -->
                <div
                    class="flex items-center justify-between border-b-2 border-dashed pb-3"
                    :style="{ borderColor: colors.grisMedio }"
                >
                    <Link :href="home()" class="flex items-center gap-1.5">
                        <Truck
                            class="h-5 w-5"
                            :style="{ color: colors.naranjaClaro }"
                            aria-hidden="true"
                        />

                        <span class="text-sm font-bold tracking-wide uppercase">
                            {{ name }}
                        </span>
                    </Link>
                </div>

                <!-- ORIGEN / DESTINO -->
                <div
                    class="mt-3 flex justify-between text-[10px]"
                    :style="{ color: colors.grisPizarra }"
                >
                    <span>
                        ORIGEN

                        <b
                            class="block text-xs"
                            :style="{ color: colors.azulOscuro }"
                        >
                            TU EMPRESA
                        </b>
                    </span>

                    <span class="text-right">
                        DESTINO

                        <b
                            class="block text-xs"
                            :style="{ color: colors.azulOscuro }"
                        >
                            PANEL
                        </b>
                    </span>
                </div>

                <!-- TÍTULO Y DESCRIPCIÓN -->
                <div v-if="title || description" class="mt-5 space-y-1">
                    <h1
                        v-if="title"
                        class="text-lg font-bold tracking-tight uppercase"
                    >
                        {{ title }}
                    </h1>

                    <p
                        v-if="description"
                        class="text-xs"
                        :style="{ color: colors.grisPizarra }"
                    >
                        {{ description }}
                    </p>
                </div>

                <!-- FORMULARIO -->
                <div class="mt-5">
                    <slot />
                </div>

                <!-- CÓDIGO DE BARRAS + SELLO -->
                <div
                    class="mt-6 flex items-end justify-between"
                    aria-hidden="true"
                >
                    <div class="flex h-7 items-stretch gap-0.5">
                        <span
                            v-for="(width, i) in barcode"
                            :key="i"
                            :style="{
                                width: `${width}px`,
                                backgroundColor: colors.azulOscuro,
                            }"
                        />
                    </div>

                    <span
                        class="-rotate-12 rounded-sm border-2 px-1.5 py-0.5 text-[10px] font-bold tracking-widest"
                        :style="{
                            borderColor: colors.naranjaClaro,
                            color: colors.naranjaClaro,
                        }"
                    >
                        ACCESO SEGURO
                    </span>
                </div>
            </div>
        </main>
    </div>
</template>

<style scoped>
.waybill :deep(label) {
    font-size: 10px;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    color: v-bind('colors.grisPizarra');
}

.waybill :deep(input:not([type='checkbox'])) {
    border-radius: 0;
    border-width: 0 0 1.5px 0;
    border-color: v-bind('colors.azulOscuro');
    background-color: transparent;
    box-shadow: none;
    padding-left: 0;
    font-family: ui-monospace, monospace;
}

.waybill :deep(input:not([type='checkbox']):focus-visible) {
    border-color: v-bind('colors.azulClaro');
    box-shadow: none;
    outline: none;
}

.waybill :deep(button[type='submit']) {
    border-radius: 4px;
    background-color: v-bind('colors.azulOscuro');
    color: #fff;
    font-family: ui-monospace, monospace;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
}
</style>
