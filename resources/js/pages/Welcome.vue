<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { dashboard, login } from '@/routes';

// Importamos nuestro objeto de colores.
import { colors } from '@/UI/Colors';
// Importamos los iconos desde nuestro archivo centralizado
import {
    Truck,
    Menu,
    X,
    Database,
    Waypoints,
    BarChart3,
    ArrowRight,
    Route,
    TrendingUp,
    PackageCheck,
} from '@/UI/Icons';
// Contenido de la landing
import { landingContent, features, stats, bars } from '@/config/landing';
import type { Component } from 'vue';

// Controla el menú desplegable en móvil
const menuOpen = ref(false);

// Nodos de la red decorativa del hero
const nodosHero = [
    { x: 900, y: 620, destacado: false, delay: 0 },
    { x: 1210, y: 430, destacado: true, delay: 0.6 },
    { x: 1400, y: 260, destacado: false, delay: 1.2 },
    { x: 980, y: 700, destacado: false, delay: 0.3 },
    { x: 1320, y: 480, destacado: true, delay: 0.9 },
    { x: 1050, y: 180, destacado: false, delay: 1.5 },
    { x: 870, y: 760, destacado: true, delay: 0.2 },
];

const datosHero = [
    { label: 'Rutas', value: 'Analizadas' },
    { label: 'Costos', value: 'Operativos' },
    { label: 'Tiempo', value: 'Distribución' },
];

interface ComponenteArquitectura {
    titulo: string;
    descripcion: string;
    acento: string;
    icono: Component;
}

const componentesArquitectura: ComponenteArquitectura[] = [
    {
        titulo: 'PostgreSQL + PostGIS',
        descripcion:
            'Base de datos operacional para gestionar entidades y eventos logísticos, junto con información geográfica relacionada con puntos y rutas de distribución.',
        acento: colors.azulClaro,
        icono: Database,
    },
    {
        titulo: 'Neo4j',
        descripcion:
            'Representación de la red de distribución mediante nodos y conexiones para evaluar caminos mínimos y posibles rutas alternativas.',
        acento: colors.naranjaClaro,
        icono: Waypoints,
    },
    {
        titulo: 'Inteligencia de Negocios',
        descripcion:
            'Consolidación y visualización de indicadores para analizar clientes, rutas, vehículos, tiempos y costos.',
        acento: colors.azulOscuro,
        icono: BarChart3,
    },
];

const pasosFlujo = [
    {
        numero: '01',
        titulo: 'Datos operacionales',
        detalle: 'Registro validado',
    },
    { numero: '02', titulo: 'Integración', detalle: 'Transformación ETL' },
    { numero: '03', titulo: 'Procesamiento', detalle: 'Análisis de red' },
    { numero: '04', titulo: 'Indicadores BI', detalle: 'Dashboards' },
];

const indicadoresBi = [
    {
        label: 'Clientes',
        titulo: 'Cantidad de envíos',
        detalle: 'Identificación de clientes con mayor volumen de envíos.',
        icono: PackageCheck,
        acento: colors.azulClaro,
    },
    {
        label: 'Rutas',
        titulo: 'Tiempos y retrasos',
        detalle: 'Análisis de rutas con mayores tiempos de distribución.',
        icono: Route,
        acento: colors.naranjaClaro,
    },
    {
        label: 'Costos',
        titulo: 'Operación y combustible',
        detalle: 'Evolución de costos operativos y consumo de combustible.',
        icono: TrendingUp,
        acento: '#22d3ee',
    },
    {
        label: 'Vehículos',
        titulo: 'Nivel de utilización',
        detalle:
            'Análisis de utilización de los diferentes tipos de vehículos.',
        icono: Truck,
        acento: '#34d399',
    },
];

const tilesDashboard = [
    { label: 'Tiempo', titulo: 'Distribución' },
    { label: 'Costos', titulo: 'Operativos' },
    { label: 'Rutas', titulo: 'Desempeño' },
    { label: 'Vehículos', titulo: 'Utilización' },
];

// Reveals al hacer scroll: los elementos con [data-reveal] aparecen suavemente
onMounted(() => {
    const observer = new IntersectionObserver(
        (entries) => {
            for (const entry of entries) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('is-visible');
                    observer.unobserve(entry.target);
                }
            }
        },
        { threshold: 0.15 },
    );

    document
        .querySelectorAll('[data-reveal]')
        .forEach((el) => observer.observe(el));
});
</script>

<template>
    <Head title="Sistema de Gestión Operacional y Analítica Logística" />

    <div class="min-h-screen bg-slate-50" :style="{ color: colors.azulOscuro }">
        <!-- NAVEGACIÓN -->
        <header
            class="sticky top-0 z-50 border-b border-white/10 backdrop-blur-md"
            :style="{ backgroundColor: 'rgba(15, 42, 71, 0.85)' }"
        >
            <nav
                class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3"
            >
                <!-- Logo -->
                <Link href="/" class="group flex items-center gap-2.5">
                    <span
                        class="flex h-8 w-8 items-center justify-center rounded-lg shadow-lg transition-transform duration-300 group-hover:-translate-y-0.5"
                        :style="{ backgroundColor: colors.naranjaClaro }"
                    >
                        <Truck
                            class="h-4.5 w-4.5 text-white"
                            aria-hidden="true"
                        />
                    </span>

                    <span
                        class="text-base font-semibold tracking-tight text-white"
                    >
                        Prototipo Logística
                    </span>
                </Link>

                <!-- Enlaces escritorio -->
                <div class="hidden items-center gap-8 text-sm md:flex">
                    <a
                        v-for="item in [
                            { href: '#objetivo', label: 'Proyecto' },
                            { href: '#arquitectura', label: 'Arquitectura' },
                            { href: '#analitica', label: 'Analítica' },
                        ]"
                        :key="item.href"
                        :href="item.href"
                        class="text-slate-300 transition-colors duration-200 hover:text-white"
                    >
                        {{ item.label }}
                    </a>

                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="rounded-lg px-4 py-2 font-medium text-white shadow-lg transition-all duration-200 hover:-translate-y-0.5 hover:opacity-90 active:scale-[0.97]"
                        :style="{ backgroundColor: colors.azulClaro }"
                    >
                        Ir al panel
                    </Link>

                    <Link
                        v-else
                        :href="login()"
                        class="rounded-lg px-4 py-2 font-medium text-white shadow-lg transition-all duration-200 hover:-translate-y-0.5 hover:opacity-90 active:scale-[0.97]"
                        :style="{ backgroundColor: colors.azulClaro }"
                    >
                        Iniciar sesión
                    </Link>
                </div>

                <!-- Botón menú móvil -->
                <button
                    type="button"
                    class="text-white md:hidden"
                    :aria-expanded="menuOpen"
                    aria-label="Abrir menú"
                    @click="menuOpen = !menuOpen"
                >
                    <X v-if="menuOpen" class="h-6 w-6" />

                    <Menu v-else class="h-6 w-6" />
                </button>
            </nav>

            <!-- Menú móvil -->
            <div
                v-if="menuOpen"
                class="flex flex-col gap-3 border-t border-white/10 px-4 py-3 text-sm md:hidden"
            >
                <a
                    href="#objetivo"
                    class="text-slate-300"
                    @click="menuOpen = false"
                >
                    Proyecto
                </a>

                <a
                    href="#arquitectura"
                    class="text-slate-300"
                    @click="menuOpen = false"
                >
                    Arquitectura
                </a>

                <a
                    href="#analitica"
                    class="text-slate-300"
                    @click="menuOpen = false"
                >
                    Analítica
                </a>

                <Link
                    v-if="$page.props.auth.user"
                    :href="dashboard()"
                    class="font-medium"
                    :style="{ color: colors.naranjaClaro }"
                >
                    Ir al panel
                </Link>

                <Link
                    v-else
                    :href="login()"
                    class="font-medium"
                    :style="{ color: colors.naranjaClaro }"
                >
                    Iniciar sesión
                </Link>
            </div>
        </header>

        <main>
            <!-- HERO -->
            <section
                class="relative overflow-hidden"
                :style="{ backgroundColor: colors.azulOscuro }"
            >
                <!-- Capa 1: imagen de red logística -->
                <img
                    src="/img/hero-network.png"
                    alt=""
                    aria-hidden="true"
                    class="absolute inset-0 h-full w-full object-cover opacity-40"
                />

                <!-- Capa 2: degradados para legibilidad -->
                <div
                    class="absolute inset-0"
                    :style="{
                        background: `linear-gradient(90deg, ${colors.azulOscuro} 0%, ${colors.azulOscuro}cc 45%, transparent 100%)`,
                    }"
                />

                <div
                    class="absolute inset-x-0 bottom-0 h-40"
                    :style="{
                        background: `linear-gradient(180deg, transparent, ${colors.azulOscuro})`,
                    }"
                />

                <!-- Capa 3: red animada (rutas con flujo) -->
                <svg
                    class="pointer-events-none absolute inset-0 h-full w-full opacity-60"
                    viewBox="0 0 1440 900"
                    preserveAspectRatio="xMidYMid slice"
                    aria-hidden="true"
                >
                    <!-- Rutas -->
                    <path
                        v-for="ruta in [
                            'M 900 620 C 1020 540, 1100 560, 1210 430',
                            'M 1210 430 C 1290 350, 1330 320, 1400 260',
                            'M 980 700 C 1120 680, 1240 600, 1320 480',
                            'M 1050 180 C 1150 240, 1230 300, 1330 340',
                        ]"
                        :key="ruta"
                        :d="ruta"
                        fill="none"
                        stroke="rgba(37, 99, 235, 0.45)"
                        stroke-width="1.5"
                        stroke-dasharray="8 12"
                        class="ruta-flujo"
                    />

                    <!-- Ruta destacada (naranja) -->
                    <path
                        d="M 870 760 C 1040 720, 1180 640, 1300 500 C 1350 440, 1380 400, 1410 370"
                        fill="none"
                        :stroke="`${colors.naranjaClaro}99`"
                        stroke-width="2"
                        stroke-dasharray="10 12"
                        class="ruta-flujo ruta-lenta"
                    />

                    <!-- Paquetes en tránsito sobre la ruta destacada -->
                    <circle
                        r="5"
                        :fill="colors.naranjaClaro"
                        class="sombra-brillo"
                    >
                        <animateMotion
                            dur="9s"
                            repeatCount="indefinite"
                            path="M 870 760 C 1040 720, 1180 640, 1300 500 C 1350 440, 1380 400, 1410 370"
                        />
                    </circle>

                    <circle r="4" fill="#60a5fa" class="sombra-brillo">
                        <animateMotion
                            dur="12s"
                            repeatCount="indefinite"
                            path="M 900 620 C 1020 540, 1100 560, 1210 430 C 1290 350, 1330 320, 1400 260"
                        />
                    </circle>

                    <!-- Nodos -->
                    <g v-for="nodo in nodosHero" :key="`${nodo.x}-${nodo.y}`">
                        <circle
                            :cx="nodo.x"
                            :cy="nodo.y"
                            :r="nodo.destacado ? 7 : 5"
                            :fill="
                                nodo.destacado ? colors.naranjaClaro : '#2563EB'
                            "
                            class="sombra-brillo nodo-pulso"
                            :style="{ animationDelay: `${nodo.delay}s` }"
                        />
                    </g>
                </svg>

                <div
                    class="relative mx-auto grid max-w-6xl items-center gap-12 px-4 pt-20 pb-16 md:min-h-[85vh] md:grid-cols-2 md:pt-28 md:pb-24"
                >
                    <!-- Texto -->
                    <div>
                        <span
                            class="animar-aparicion inline-flex items-center gap-2 rounded-full border px-3.5 py-1.5 text-xs font-medium tracking-wide"
                            :style="{
                                borderColor: `${colors.naranjaClaro}66`,
                                color: '#fdba74',
                                backgroundColor: `${colors.naranjaClaro}14`,
                            }"
                        >
                            <span
                                class="h-1.5 w-1.5 animate-pulse rounded-full"
                                :style="{
                                    backgroundColor: colors.naranjaClaro,
                                }"
                            />

                            {{ landingContent.badge }}
                        </span>

                        <h1
                            class="animar-aparicion mt-6 max-w-xl text-4xl leading-[1.1] font-bold tracking-tight text-white md:text-6xl"
                            style="animation-delay: 0.1s"
                        >
                            Convierte tus datos logísticos en
                            <span class="texto-degradado">
                                decisiones inteligentes
                            </span>
                        </h1>

                        <p
                            class="animar-aparicion mt-6 max-w-lg text-base leading-relaxed text-slate-300 md:text-lg"
                            style="animation-delay: 0.2s"
                        >
                            {{ landingContent.description }}
                        </p>

                        <div
                            class="animar-aparicion mt-8 flex flex-col gap-3 sm:flex-row"
                            style="animation-delay: 0.3s"
                        >
                            <a
                                href="#analitica"
                                class="grupo-boton rounded-lg px-6 py-3 text-center text-sm font-semibold text-white shadow-xl transition-all duration-200 hover:-translate-y-0.5 active:scale-[0.97]"
                                :style="{
                                    backgroundColor: colors.naranjaClaro,
                                }"
                            >
                                <span class="inline-flex items-center gap-2">
                                    {{ landingContent.primaryButton }}

                                    <ArrowRight
                                        class="grupo-boton-hover:translate-x-1 h-4 w-4 transition-transform duration-200"
                                        aria-hidden="true"
                                    />
                                </span>
                            </a>

                            <Link
                                :href="login()"
                                class="rounded-lg border border-white/25 px-6 py-3 text-center text-sm font-medium text-white backdrop-blur-sm transition-all duration-200 hover:border-white/50 hover:bg-white/10"
                            >
                                {{ landingContent.secondaryButton }}
                            </Link>
                        </div>
                    </div>

                    <!-- Tarjeta de indicadores -->
                    <div
                        class="animar-aparicion tarjeta-vidrio relative md:justify-self-end"
                        style="animation-delay: 0.4s"
                    >
                        <div class="mb-5 flex items-center justify-between">
                            <div>
                                <span
                                    class="flex items-center gap-2 text-xs text-slate-400"
                                >
                                    <span
                                        class="h-2 w-2 animate-pulse rounded-full bg-emerald-400"
                                    />

                                    Indicadores en vivo
                                </span>

                                <h2
                                    class="mt-1.5 text-base font-semibold text-white"
                                >
                                    Desempeño logístico
                                </h2>
                            </div>

                            <span
                                class="rounded-lg px-3 py-1 text-xs font-semibold"
                                :style="{
                                    color: colors.naranjaClaro,
                                    backgroundColor: `${colors.naranjaClaro}1f`,
                                }"
                            >
                                BI
                            </span>
                        </div>

                        <!-- Gráfico -->
                        <div
                            class="flex h-32 items-end gap-2.5"
                            role="img"
                            aria-label="Representación de indicadores de desempeño logístico"
                        >
                            <div
                                v-for="(height, i) in bars"
                                :key="i"
                                class="barra-crecer flex-1 rounded-t-md"
                                :style="{
                                    height: `${height}%`,
                                    backgroundColor:
                                        i === bars.length - 1
                                            ? colors.naranjaClaro
                                            : colors.azulClaro,
                                    animationDelay: `${0.5 + i * 0.08}s`,
                                }"
                            />
                        </div>

                        <!-- Datos -->
                        <div
                            class="mt-5 grid grid-cols-3 gap-3 border-t border-white/10 pt-5"
                        >
                            <div v-for="dato in datosHero" :key="dato.label">
                                <span class="text-xs text-slate-400">
                                    {{ dato.label }}
                                </span>

                                <p
                                    class="mt-0.5 text-sm font-semibold text-white"
                                >
                                    {{ dato.value }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Banda de métricas -->
                <div class="relative border-t border-white/10">
                    <div
                        class="mx-auto grid max-w-6xl grid-cols-2 divide-x divide-white/10 px-4 md:grid-cols-4"
                    >
                        <div
                            v-for="stat in stats"
                            :key="stat.label"
                            class="flex flex-col items-center gap-1 py-6 text-center"
                        >
                            <span
                                class="text-xl font-bold tracking-tight md:text-2xl"
                                :style="{ color: '#93c5fd' }"
                            >
                                {{ stat.value }}
                            </span>

                            <span class="text-xs text-slate-300 md:text-sm">
                                {{ stat.label }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Transición suave hacia las secciones claras -->
                <div
                    class="h-16"
                    :style="{
                        background: `linear-gradient(180deg, ${colors.azulOscuro}, #f8fafc)`,
                    }"
                />
            </section>

            <!-- OBJETIVO DEL PROYECTO -->
            <section
                id="objetivo"
                class="relative mx-auto max-w-6xl scroll-mt-20 px-4 py-16 md:py-24"
            >
                <div
                    class="grid items-center gap-12 md:grid-cols-2"
                    data-reveal
                >
                    <div>
                        <span
                            class="text-xs font-semibold tracking-[0.2em] uppercase"
                            :style="{ color: colors.naranjaClaro }"
                        >
                            Propósito del proyecto
                        </span>

                        <h2
                            class="mt-3 text-3xl font-bold tracking-tight md:text-4xl"
                        >
                            Gestión operacional orientada al
                            <span class="texto-subrayado">análisis</span> y
                            optimización
                        </h2>

                        <p
                            class="mt-5 text-base leading-relaxed text-slate-600"
                        >
                            Centraliza la información de tu operación logística
                            y obtén una visión clara del comportamiento de tus
                            envíos, rutas y recursos. Consulta los datos que
                            necesitas para entender cómo se mueve tu operación y
                            detectar situaciones que requieren atención.
                        </p>

                        <p
                            class="mt-4 text-base leading-relaxed text-slate-600"
                        >
                            Convierte los datos operacionales en información
                            útil mediante indicadores, análisis históricos y
                            visualizaciones que permiten explorar tiempos de
                            distribución, costos, consumo de combustible,
                            utilización de vehículos y desempeño de las rutas.
                        </p>
                    </div>

                    <!-- Imagen de apoyo -->
                    <div class="relative">
                        <!-- Marco de acento desplazado -->
                        <div
                            class="absolute -top-3 -right-3 h-full w-full rounded-2xl"
                            :style="{
                                backgroundColor: `${colors.azulClaro}1a`,
                            }"
                            aria-hidden="true"
                        />

                        <img
                            src="/img/proposito-logistica.png"
                            alt="Camiones de reparto en una vía de distribución vista aérea"
                            class="relative rounded-2xl border border-slate-200 shadow-xl"
                        />

                        <!-- Insignia flotante -->
                        <div
                            class="absolute -bottom-4 -left-4 flex items-center gap-2.5 rounded-xl px-4 py-3 text-white shadow-lg"
                            :style="{ backgroundColor: colors.azulOscuro }"
                        >
                            <Waypoints
                                class="h-5 w-5"
                                :style="{ color: colors.naranjaClaro }"
                                aria-hidden="true"
                            />

                            <div>
                                <p class="text-xs leading-tight font-semibold">
                                    Red de distribución
                                </p>

                                <p class="text-[11px] text-slate-300">
                                    análisis de rutas y conexiones
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ARQUITECTURA -->
            <section
                id="arquitectura"
                class="scroll-mt-20 bg-white"
                :style="{
                    backgroundImage: `linear-gradient(${colors.azulOscuro}05 1px, transparent 1px), linear-gradient(90deg, ${colors.azulOscuro}05 1px, transparent 1px)`,
                    backgroundSize: '48px 48px',
                }"
            >
                <div class="mx-auto max-w-6xl px-4 py-16 md:py-24">
                    <div class="max-w-3xl" data-reveal>
                        <span
                            class="text-xs font-semibold tracking-[0.2em] uppercase"
                            :style="{ color: colors.naranjaClaro }"
                        >
                            Arquitectura de datos
                        </span>

                        <h2
                            class="mt-3 text-3xl font-bold tracking-tight md:text-4xl"
                        >
                            Del dato operacional al análisis
                        </h2>

                        <p
                            class="mt-5 text-base leading-relaxed text-slate-600"
                        >
                            La arquitectura desacoplada permite cubrir el ciclo
                            de vida de los datos, desde su recopilación y
                            registro hasta su transformación, almacenamiento y
                            análisis.
                        </p>
                    </div>

                    <div class="mt-10 grid gap-5 md:grid-cols-3">
                        <article
                            v-for="componente in componentesArquitectura"
                            :key="componente.titulo"
                            class="tarjeta-arquitectura group relative overflow-hidden rounded-xl border border-slate-200 bg-white p-6"
                            :style="{ '--acento': componente.acento }"
                            data-reveal
                        >
                            <span
                                class="absolute inset-x-0 top-0 h-1 origin-left scale-x-0 transition-transform duration-300 group-hover:scale-x-100"
                                :style="{ backgroundColor: componente.acento }"
                            />

                            <div
                                class="flex h-11 w-11 items-center justify-center rounded-xl text-white shadow-md transition-transform duration-300 group-hover:scale-110"
                                :style="{ backgroundColor: componente.acento }"
                            >
                                <component
                                    :is="componente.icono"
                                    class="h-5.5 w-5.5"
                                    aria-hidden="true"
                                />
                            </div>

                            <h3 class="mt-5 text-lg font-semibold">
                                {{ componente.titulo }}
                            </h3>

                            <p
                                class="mt-2 text-sm leading-relaxed text-slate-600"
                            >
                                {{ componente.descripcion }}
                            </p>
                        </article>
                    </div>

                    <!-- Flujo de datos -->
                    <div
                        class="tarjeta-elevada mt-10 rounded-xl border border-slate-200 bg-white p-6 md:p-8"
                        data-reveal
                    >
                        <div class="relative">
                            <!-- Línea conectora animada (solo escritorio) -->
                            <div
                                class="absolute top-5 right-[12%] left-[12%] hidden h-0.5 md:block"
                                :style="{
                                    backgroundColor: `${colors.azulClaro}26`,
                                }"
                            >
                                <div
                                    class="linea-progreso h-full"
                                    :style="{
                                        backgroundImage: `repeating-linear-gradient(90deg, ${colors.azulClaro} 0 8px, transparent 8px 16px)`,
                                    }"
                                />
                            </div>

                            <div
                                class="flex flex-col items-center justify-between gap-6 text-center md:flex-row"
                            >
                                <div
                                    v-for="paso in pasosFlujo"
                                    :key="paso.numero"
                                    class="relative flex-1"
                                >
                                    <span
                                        class="relative z-10 mx-auto flex h-10 w-10 items-center justify-center rounded-full border-2 bg-white text-sm font-bold"
                                        :style="{
                                            borderColor: colors.azulClaro,
                                            color: colors.azulClaro,
                                        }"
                                    >
                                        {{ paso.numero }}
                                    </span>

                                    <p class="mt-3 text-sm font-semibold">
                                        {{ paso.titulo }}
                                    </p>

                                    <p class="mt-1 text-xs text-slate-500">
                                        {{ paso.detalle }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ANALÍTICA -->
            <section id="analitica" class="scroll-mt-20">
                <div class="mx-auto max-w-6xl px-4 py-8 md:py-12">
                    <div
                        class="relative overflow-hidden rounded-2xl px-6 py-14 shadow-2xl md:px-12 md:py-20"
                        :style="{ backgroundColor: colors.azulOscuro }"
                    >
                        <!-- Decoración: brillos difuminados -->
                        <div
                            class="absolute -top-32 -right-32 h-80 w-80 rounded-full"
                            :style="{
                                backgroundColor: `${colors.azulClaro}26`,
                                filter: 'blur(80px)',
                            }"
                        />

                        <div
                            class="absolute -bottom-32 -left-32 h-80 w-80 rounded-full"
                            :style="{
                                backgroundColor: `${colors.naranjaClaro}1a`,
                                filter: 'blur(80px)',
                            }"
                        />
                        <div class="max-w-3xl" data-reveal>
                            <span
                                class="text-xs font-semibold tracking-[0.2em] uppercase"
                                :style="{ color: colors.naranjaClaro }"
                            >
                                Inteligencia de Negocios
                            </span>

                            <h2
                                class="mt-3 text-3xl font-bold tracking-tight text-white md:text-4xl"
                            >
                                {{ landingContent.featuresTitle }}
                            </h2>

                            <p
                                class="mt-5 text-base leading-relaxed text-slate-300"
                            >
                                {{ landingContent.featuresDescription }}
                            </p>
                        </div>

                        <!-- Capacidades: columnas editoriales separadas por filetes -->
                        <div
                            class="mt-12 grid divide-y divide-white/10 border-y border-white/10 md:grid-cols-3 md:divide-x md:divide-y-0"
                            data-reveal
                        >
                            <article
                                v-for="feature in features"
                                :key="feature.title"
                                class="grupo flex gap-4 py-7 md:flex-col md:px-8 md:first:pl-0 md:last:pr-0"
                            >
                                <span
                                    class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-white/15 transition-colors duration-300 group-hover:border-white/35"
                                >
                                    <component
                                        :is="feature.icon"
                                        class="h-5 w-5 transition-transform duration-300 group-hover:scale-110"
                                        :class="feature.color"
                                        aria-hidden="true"
                                    />
                                </span>

                                <div>
                                    <h3
                                        class="text-base font-semibold text-white"
                                    >
                                        {{ feature.title }}
                                    </h3>

                                    <p
                                        class="mt-1.5 text-sm leading-relaxed text-slate-300"
                                    >
                                        {{ feature.description }}
                                    </p>
                                </div>
                            </article>
                        </div>

                        <!-- Indicadores: franja de métricas separadas por filetes -->
                        <div
                            class="mt-4 grid grid-cols-1 gap-y-8 sm:grid-cols-2 lg:grid-cols-4 lg:gap-y-0"
                            data-reveal
                        >
                            <div
                                v-for="(indicador, i) in indicadoresBi"
                                :key="indicador.label"
                                class="relative lg:px-7 lg:first:pl-0 lg:last:pr-0"
                            >
                                <!-- Filete vertical entre métricas -->
                                <span
                                    v-if="i > 0"
                                    class="absolute inset-y-1 left-0 hidden w-px lg:block"
                                    :style="{
                                        backgroundColor:
                                            'rgba(255,255,255,0.1)',
                                    }"
                                />

                                <div class="flex items-center gap-2">
                                    <component
                                        :is="indicador.icono"
                                        class="h-4 w-4"
                                        :style="{ color: indicador.acento }"
                                        aria-hidden="true"
                                    />

                                    <span
                                        class="text-xs font-medium tracking-wide uppercase"
                                        style="color: #94a3b8"
                                    >
                                        {{ indicador.label }}
                                    </span>
                                </div>

                                <p
                                    class="mt-2.5 text-sm font-semibold text-white"
                                >
                                    {{ indicador.titulo }}
                                </p>

                                <p
                                    class="mt-1.5 text-xs leading-relaxed text-slate-400"
                                >
                                    {{ indicador.detalle }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- DASHBOARD -->
            <section
                class="bg-white"
                :style="{
                    backgroundImage: `linear-gradient(${colors.azulOscuro}05 1px, transparent 1px), linear-gradient(90deg, ${colors.azulOscuro}05 1px, transparent 1px)`,
                    backgroundSize: '48px 48px',
                }"
            >
                <div
                    class="mx-auto grid max-w-6xl items-center gap-10 px-4 py-16 md:grid-cols-2 md:py-24"
                >
                    <div data-reveal>
                        <span
                            class="text-xs font-semibold tracking-[0.2em] uppercase"
                            :style="{ color: colors.naranjaClaro }"
                        >
                            Panel de control
                        </span>

                        <h2
                            class="mt-3 text-3xl font-bold tracking-tight md:text-4xl"
                        >
                            Una vista integrada de la operación
                        </h2>

                        <p
                            class="mt-5 text-base leading-relaxed text-slate-600"
                        >
                            Los resultados del procesamiento y análisis se
                            presentan mediante un panel de control interactivo
                            que concentra los principales indicadores de la
                            operación logística.
                        </p>

                        <p
                            class="mt-4 text-base leading-relaxed text-slate-600"
                        >
                            La información puede consultarse desde diferentes
                            perspectivas para analizar rutas, vehículos,
                            clientes, tiempos y costos.
                        </p>
                    </div>

                    <!-- Vista conceptual del dashboard -->
                    <div
                        class="tarjeta-elevada overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xl"
                        data-reveal
                    >
                        <!-- Barra superior del panel -->
                        <div
                            class="flex items-center gap-2 border-b border-slate-100 px-5 py-3"
                        >
                            <span
                                v-for="foco in [
                                    '#f87171',
                                    '#fbbf24',
                                    '#34d399',
                                ]"
                                :key="foco"
                                class="h-2.5 w-2.5 rounded-full"
                                :style="{ backgroundColor: foco }"
                            />

                            <span class="ml-3 text-xs text-slate-400">
                                panel · indicadores logísticos
                            </span>
                        </div>

                        <div class="p-5">
                            <div class="grid grid-cols-2 gap-4">
                                <div
                                    v-for="tile in tilesDashboard"
                                    :key="tile.label"
                                    class="rounded-lg bg-slate-50 p-4 transition-colors duration-200 hover:bg-slate-100"
                                >
                                    <span class="text-xs text-slate-500">
                                        {{ tile.label }}
                                    </span>

                                    <p class="mt-1 text-sm font-semibold">
                                        {{ tile.titulo }}
                                    </p>
                                </div>
                            </div>

                            <div
                                class="mt-4 rounded-lg p-4 text-white"
                                :style="{
                                    backgroundColor: colors.azulOscuro,
                                }"
                            >
                                <span class="text-xs text-slate-400">
                                    Flujo de información
                                </span>

                                <p
                                    class="mt-1 flex flex-wrap items-center gap-1.5 text-sm font-medium"
                                >
                                    <template
                                        v-for="(etapa, i) in [
                                            'Datos',
                                            'Procesamiento',
                                            'Indicadores',
                                            'Análisis',
                                        ]"
                                        :key="etapa"
                                    >
                                        <span>{{ etapa }}</span>

                                        <ArrowRight
                                            v-if="i < 3"
                                            class="h-3.5 w-3.5"
                                            :style="{
                                                color: colors.naranjaClaro,
                                            }"
                                            aria-hidden="true"
                                        />
                                    </template>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- CTA -->
            <section class="mx-auto max-w-6xl px-4 py-16">
                <div
                    class="relative overflow-hidden rounded-2xl px-6 py-12 text-center md:px-12 md:py-16"
                    :style="{
                        backgroundImage: `linear-gradient(120deg, ${colors.azulClaro}, ${colors.azulOscuro})`,
                    }"
                    data-reveal
                >
                    <!-- Decoración -->
                    <div
                        class="absolute -top-24 -right-24 h-64 w-64 rounded-full"
                        :style="{
                            backgroundColor: `${colors.naranjaClaro}22`,
                            filter: 'blur(48px)',
                        }"
                    />

                    <h2
                        class="relative text-2xl font-bold text-white md:text-3xl"
                    >
                        {{ landingContent.contactTitle }}
                    </h2>

                    <p
                        class="relative mx-auto mt-4 max-w-2xl text-base leading-relaxed text-blue-100"
                    >
                        {{ landingContent.contactDescription }}
                    </p>

                    <Link
                        :href="login()"
                        class="grupo-boton relative mt-8 inline-flex items-center gap-2 rounded-lg px-7 py-3 text-sm font-semibold shadow-xl transition-all duration-200 hover:-translate-y-0.5 active:scale-[0.97]"
                        :style="{
                            backgroundColor: colors.naranjaClaro,
                            color: '#ffffff',
                        }"
                    >
                        Acceder al sistema

                        <ArrowRight
                            class="grupo-boton-hover:translate-x-1 h-4 w-4 transition-transform duration-200"
                            aria-hidden="true"
                        />
                    </Link>
                </div>
            </section>
        </main>

        <!-- FOOTER -->
        <footer
            class="py-6 text-center text-xs text-slate-400"
            :style="{ backgroundColor: colors.azulOscuro }"
        >
            © {{ new Date().getFullYear() }} Prototipo Logística · Sistema de
            Gestión Operacional y Analítica
        </footer>
    </div>
</template>

<style scoped>
/* ===== Animaciones de entrada ===== */
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

/* ===== Red animada del hero ===== */
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

/* ===== Reveals al hacer scroll ===== */
[data-reveal] {
    opacity: 0;
    transform: translateY(28px);
    transition:
        opacity 0.7s cubic-bezier(0.22, 1, 0.36, 1),
        transform 0.7s cubic-bezier(0.22, 1, 0.36, 1);
}

[data-reveal].is-visible {
    opacity: 1;
    transform: translateY(0);
}

/* ===== Superficies ===== */
.tarjeta-vidrio {
    border-radius: 1rem;
    border: 1px solid rgb(255 255 255 / 0.12);
    background: rgb(255 255 255 / 0.06);
    padding: 1.5rem;
    backdrop-filter: blur(12px);
    box-shadow: 0 24px 48px -16px rgb(2 6 23 / 0.5);
}

.tarjeta-elevada {
    transition:
        transform 0.3s cubic-bezier(0.22, 1, 0.36, 1),
        box-shadow 0.3s ease;
}

.tarjeta-elevada:hover {
    transform: translateY(-4px);
    box-shadow:
        0 4px 6px -1px rgb(15 42 71 / 0.06),
        0 20px 36px -12px rgb(15 42 71 / 0.18);
}

/* Tarjetas de arquitectura: al pasar el cursor adoptan su color de acento */
.tarjeta-arquitectura {
    transition:
        transform 0.3s cubic-bezier(0.22, 1, 0.36, 1),
        border-color 0.3s ease,
        box-shadow 0.3s ease;
}

.tarjeta-arquitectura:hover {
    transform: translateY(-5px);
    border-color: var(--acento);
    box-shadow: 0 24px 40px -20px var(--acento);
}

/* ===== Detalles tipográficos ===== */
.texto-degradado {
    background-image: linear-gradient(100deg, #fdba74, #f97316 70%, #fb923c);
    background-clip: text;
    color: transparent;
}

.texto-subrayado {
    background-image: linear-gradient(90deg, #f97316, #2563eb);
    background-repeat: no-repeat;
    background-position: 0 100%;
    background-size: 100% 0.14em;
    padding-bottom: 0.08em;
}

/* ===== Línea de progreso del flujo ===== */
.linea-progreso {
    animation: desplazar-linea 1.2s linear infinite;
}

@keyframes desplazar-linea {
    to {
        background-position-x: 16px;
    }
}

/* ===== Decoración ===== */
.rotacion-lenta {
    animation: rotar 24s linear infinite;
}

@keyframes rotar {
    to {
        transform: rotate(360deg);
    }
}

/* ===== Accesibilidad: respetar la preferencia de movimiento reducido ===== */
@media (prefers-reduced-motion: reduce) {
    .animar-aparicion,
    .barra-crecer,
    .ruta-flujo,
    .nodo-pulso,
    .linea-progreso,
    .rotacion-lenta {
        animation: none;
    }

    [data-reveal] {
        opacity: 1;
        transform: none;
        transition: none;
    }
}
</style>
