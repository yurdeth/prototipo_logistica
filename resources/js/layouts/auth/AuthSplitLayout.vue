<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { colors } from '@/UI/Colors';
import { Truck } from '@/UI/Icons';
import { landingContent } from '@/config/landing';
import { home } from '@/routes';

const page = usePage();
const name = page.props.name;

defineProps<{
    title?: string;
    description?: string;
}>();

// Barras del motivo decorativo inferior
const barras = [35, 55, 40, 70, 50, 85, 65, 100];
</script>

<template>
    <div class="grid min-h-svh lg:grid-cols-2">
        <!-- Panel de marca -->
        <div
            class="relative hidden flex-col justify-between overflow-hidden p-10 text-white lg:flex"
            :style="{ backgroundColor: colors.azulOscuro }"
        >
            <!-- Fondo: red logística -->
            <img
                src="/img/hero-network.png"
                alt=""
                aria-hidden="true"
                class="absolute inset-0 h-full w-full object-cover opacity-35"
            />

            <div
                class="absolute inset-0"
                :style="{
                    background: `linear-gradient(200deg, transparent 30%, ${colors.azulOscuro}cc 75%, ${colors.azulOscuro} 100%)`,
                }"
            />

            <!-- Red animada -->
            <svg
                class="pointer-events-none absolute inset-0 h-full w-full opacity-60"
                viewBox="0 0 800 900"
                preserveAspectRatio="xMidYMid slice"
                aria-hidden="true"
            >
                <path
                    v-for="ruta in [
                        'M 520 640 C 610 560, 660 520, 730 420',
                        'M 640 700 C 700 640, 730 580, 780 500',
                        'M 560 780 C 660 740, 720 680, 770 600',
                    ]"
                    :key="ruta"
                    :d="ruta"
                    fill="none"
                    stroke="rgba(37, 99, 235, 0.45)"
                    stroke-width="1.5"
                    stroke-dasharray="8 12"
                    class="ruta-flujo"
                />

                <path
                    d="M 470 850 C 600 800, 690 720, 780 560"
                    fill="none"
                    :stroke="`${colors.naranjaClaro}99`"
                    stroke-width="2"
                    stroke-dasharray="10 12"
                    class="ruta-flujo ruta-lenta"
                />

                <circle r="4" :fill="colors.naranjaClaro" class="sombra-brillo">
                    <animateMotion
                        dur="8s"
                        repeatCount="indefinite"
                        path="M 470 850 C 600 800, 690 720, 780 560"
                    />
                </circle>

                <circle
                    v-for="nodo in [
                        { x: 520, y: 640, naranja: false },
                        { x: 730, y: 420, naranja: true },
                        { x: 640, y: 700, naranja: false },
                        { x: 780, y: 500, naranja: true },
                        { x: 470, y: 850, naranja: false },
                    ]"
                    :key="`${nodo.x}-${nodo.y}`"
                    :cx="nodo.x"
                    :cy="nodo.y"
                    :r="nodo.naranja ? 6 : 4.5"
                    :fill="nodo.naranja ? colors.naranjaClaro : '#2563EB'"
                    class="sombra-brillo nodo-pulso"
                />
            </svg>

            <Link
                :href="home()"
                class="animar-aparicion relative z-10 flex items-center gap-2.5 text-base font-semibold tracking-tight"
            >
                <span
                    class="flex h-9 w-9 items-center justify-center rounded-lg shadow-lg"
                    :style="{ backgroundColor: colors.naranjaClaro }"
                >
                    <Truck class="h-5 w-5 text-white" aria-hidden="true" />
                </span>

                {{ name }}
            </Link>

            <div
                class="animar-aparicion relative z-10 max-w-md"
                style="animation-delay: 0.15s"
            >
                <span
                    class="inline-flex items-center gap-2 rounded-full border px-3.5 py-1.5 text-xs font-medium"
                    :style="{
                        borderColor: `${colors.naranjaClaro}66`,
                        color: '#fdba74',
                        backgroundColor: `${colors.naranjaClaro}14`,
                    }"
                >
                    <span
                        class="h-1.5 w-1.5 animate-pulse rounded-full"
                        :style="{ backgroundColor: colors.naranjaClaro }"
                    />

                    {{ landingContent.badge }}
                </span>

                <h2
                    class="mt-5 text-2xl leading-tight font-bold tracking-tight md:text-3xl"
                >
                    {{ landingContent.title }}
                </h2>

                <p class="mt-4 text-sm leading-relaxed text-slate-300">
                    {{ landingContent.description }}
                </p>
            </div>

            <!-- Motivo decorativo de barras -->
            <div
                class="animar-aparicion relative z-10 flex h-16 items-end gap-1.5"
                style="animation-delay: 0.3s"
                aria-hidden="true"
            >
                <div
                    v-for="(height, i) in barras"
                    :key="i"
                    class="barra-crecer w-6 rounded-t"
                    :style="{
                        height: `${height}%`,
                        backgroundColor:
                            i === barras.length - 1
                                ? colors.naranjaClaro
                                : colors.azulClaro,
                        animationDelay: `${0.4 + i * 0.08}s`,
                    }"
                />
            </div>
        </div>

        <!-- Panel del formulario -->
        <div
            class="relative flex flex-col items-center justify-center gap-6 overflow-hidden bg-background p-6 md:p-10"
        >
            <!-- Costura que integra ambos paneles (azul → naranja) -->
            <div
                class="absolute inset-y-0 left-0 z-10 hidden w-px lg:block"
                :style="{
                    backgroundImage: `linear-gradient(180deg, ${colors.azulClaro}, ${colors.naranjaClaro})`,
                }"
                aria-hidden="true"
            />

            <!-- Brillo azul que se asoma desde el panel de marca -->
            <div
                class="absolute -top-32 -left-32 h-[28rem] w-[28rem] rounded-full"
                :style="{
                    backgroundColor: `${colors.azulClaro}33`,
                    filter: 'blur(96px)',
                }"
                aria-hidden="true"
            />

            <div
                class="absolute -bottom-32 -left-32 h-96 w-96 rounded-full"
                :style="{
                    backgroundColor: `${colors.naranjaClaro}1f`,
                    filter: 'blur(96px)',
                }"
                aria-hidden="true"
            />

            <!-- Ruta decorativa discreta en la esquina inferior -->
            <svg
                class="pointer-events-none absolute -right-6 -bottom-6 h-48 w-48 opacity-[0.07]"
                viewBox="0 0 200 200"
                aria-hidden="true"
            >
                <path
                    d="M -10 160 C 40 120, 80 140, 120 90 S 180 40, 220 60"
                    fill="none"
                    :stroke="colors.azulOscuro"
                    stroke-width="6"
                    stroke-linecap="round"
                    stroke-dasharray="1 18"
                />
            </svg>

            <div class="animar-suave relative z-10 w-full max-w-sm">
                <!-- Logo en pantallas pequeñas -->
                <div class="mb-8 flex flex-col items-center gap-2 lg:hidden">
                    <span
                        class="flex h-9 w-9 items-center justify-center rounded-lg shadow-md"
                        :style="{ backgroundColor: colors.naranjaClaro }"
                    >
                        <Truck class="h-5 w-5 text-white" aria-hidden="true" />
                    </span>

                    <span class="text-sm font-medium text-foreground">
                        {{ name }}
                    </span>
                </div>

                <div
                    class="flex flex-col space-y-2 text-center"
                    v-if="title || description"
                >
                    <h1
                        class="text-xl font-semibold tracking-tight text-foreground"
                        v-if="title"
                    >
                        {{ title }}
                    </h1>

                    <p class="text-sm text-muted-foreground" v-if="description">
                        {{ description }}
                    </p>
                </div>

                <div class="mt-6">
                    <slot />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.animar-aparicion {
    animation: aparecer 0.7s cubic-bezier(0.22, 1, 0.36, 1) both;
}

@keyframes aparecer {
    from {
        opacity: 0;
        transform: translateY(24px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Entrada más sutil para el formulario (también en móvil) */
.animar-suave {
    animation: aparecer-suave 0.5s ease-out both;
    animation-delay: 0.1s;
}

@keyframes aparecer-suave {
    from {
        opacity: 0;
        transform: translateY(12px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.barra-crecer {
    animation: crecer 0.8s cubic-bezier(0.22, 1, 0.36, 1) both;
    transform-origin: bottom;
}

@keyframes crecer {
    from {
        transform: scaleY(0);
    }

    to {
        transform: scaleY(1);
    }
}

.ruta-flujo {
    animation: fluir 2.5s linear infinite;
}

.ruta-lenta {
    animation-duration: 4s;
}

@keyframes fluir {
    to {
        stroke-dashoffset: -20;
    }
}

.nodo-pulso {
    animation: latir 3s ease-in-out infinite;
}

@keyframes latir {
    0%,
    100% {
        opacity: 0.55;
    }

    50% {
        opacity: 1;
    }
}

.sombra-brillo {
    filter: drop-shadow(0 0 6px currentColor);
}

@media (prefers-reduced-motion: reduce) {
    .animar-aparicion,
    .animar-suave,
    .barra-crecer,
    .ruta-flujo,
    .nodo-pulso {
        animation: none;
    }
}
</style>
