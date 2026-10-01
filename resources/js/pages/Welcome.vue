<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { dashboard, login } from '@/routes';

// Importamos nuestro objeto de colores.
import { colors } from '@/UI/Colors';
// Importamos los iconos desde nuestro archivo centralizado
import { Truck, Menu, X } from '@/UI/Icons';
// Contenido de la landing
import { landingContent, features, stats, bars} from '@/config/landing';

// Controla el menú desplegable en móvil
const menuOpen = ref(false);
</script>

<template>
    <Head title="Sistema de Gestión Operacional y Analítica Logística" />

    <!-- Contenedor principal -->
    <div class="min-h-screen bg-white" :style="{ color: colors.azulOscuro }" >
        <!-- NAVEGACIÓN -->
        <header class="border-b border-slate-200 bg-white">
            <nav class="mx-auto flex max-w-6xl items-center justify-between px-4 py-3" >
                <!-- Logo -->
                <Link href="/" class="flex items-center gap-2">
                    <span class="flex h-7 w-7 items-center justify-center rounded-lg"
                        :style="{ backgroundColor: colors.naranjaClaro }" >
                        <Truck
                            class="h-4 w-4 text-white"
                            aria-hidden="true"
                        />
                    </span>

                    <span class="text-base font-medium">
                        prototipo Logistica
                    </span>
                </Link>

                <!-- Enlaces escritorio -->
                <div class="hidden items-center gap-6 text-sm md:flex">
                    <a href="#objetivo" class="text-slate-600 hover:opacity-70" >
                        Proyecto
                    </a>

                    <a href="#arquitectura" class="text-slate-600 hover:opacity-70" >
                        Arquitectura
                    </a>

                    <a href="#analitica" class="text-slate-600 hover:opacity-70" >
                        Analítica
                    </a>

                    <Link
                        v-if="$page.props.auth.user"
                        :href="dashboard()"
                        class="rounded-lg px-4 py-2 font-medium text-white hover:opacity-90"
                        :style="{ backgroundColor: colors.azulClaro }"
                    >
                        Ir al panel
                    </Link>

                    <Link
                        v-else
                        :href="login()"
                        class="rounded-lg px-4 py-2 font-medium text-white hover:opacity-90"
                        :style="{ backgroundColor: colors.azulClaro }"
                    >
                        Iniciar sesión
                    </Link>
                </div>

                <!-- Botón menú móvil -->
                <button
                    type="button"
                    class="md:hidden"
                    :aria-expanded="menuOpen"
                    aria-label="Abrir menú"
                    @click="menuOpen = !menuOpen"
                >
                    <X
                        v-if="menuOpen"
                        class="h-6 w-6"
                    />

                    <Menu
                        v-else
                        class="h-6 w-6"
                    />
                </button>
            </nav>

            <!-- Menú móvil -->
            <div v-if="menuOpen" class="flex flex-col gap-3 border-t border-slate-200 px-4 py-3 text-sm md:hidden" >
                <a href="#objetivo" @click="menuOpen = false" >
                    Proyecto
                </a>

                <a href="#arquitectura" @click="menuOpen = false">
                    Arquitectura
                </a>

                <a href="#analitica" @click="menuOpen = false" >
                    Analítica
                </a>

                <Link
                    v-if="$page.props.auth.user"
                    :href="dashboard()"
                    class="font-medium"
                    :style="{ color: colors.azulClaro }"
                >
                    Ir al panel
                </Link>

                <Link
                    v-else
                    :href="login()"
                    class="font-medium"
                    :style="{ color: colors.azulClaro }"
                >
                    Iniciar sesión
                </Link>
            </div>
        </header>

        <main>
            <section
                :style="{ backgroundColor: colors.azulOscuro }"
            >
                <div
                    class="mx-auto grid max-w-6xl items-center gap-10 px-4 py-12 md:grid-cols-2 md:py-20"
                >
                    <!-- Texto -->
                    <div>
                        <span
                            class="inline-block rounded-full border px-3 py-1 text-xs text-orange-300"
                            :style="{
                                borderColor: colors.naranjaClaro,
                            }"
                        >
                            {{ landingContent.badge }}
                        </span>

                        <h1
                            class="mt-4 text-3xl font-medium leading-tight text-white md:text-5xl"
                        >
                            {{ landingContent.title }}
                        </h1>

                        <p
                            class="mt-4 max-w-xl text-sm leading-relaxed text-slate-300 md:text-base"
                        >
                            {{ landingContent.description }}
                        </p>

                        <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                            <a
                                href="#analitica"
                                class="rounded-lg px-6 py-3 text-center text-sm font-medium text-white hover:opacity-90"
                                :style="{
                                    backgroundColor:
                                        colors.naranjaClaro,
                                }"
                            >
                                {{ landingContent.primaryButton }}
                            </a>

                            <Link
                                :href="login()"
                                class="rounded-lg border border-slate-500 px-6 py-3 text-center text-sm text-white hover:bg-white/10"
                            >
                                {{ landingContent.secondaryButton }}
                            </Link>
                        </div>
                    </div>

                    <!-- Tarjeta visual -->
                    <div
                        class="rounded-xl bg-white p-4 shadow-xl md:p-6"
                    >
                        <div
                            class="mb-5 flex items-center justify-between"
                        >
                            <div>
                                <span
                                    class="text-xs text-slate-500"
                                >
                                    Indicadores de operación
                                </span>

                                <h2
                                    class="mt-1 text-sm font-medium text-slate-800 md:text-base"
                                >
                                    Desempeño logístico
                                </h2>
                            </div>

                            <span
                                class="rounded-lg bg-green-50 px-3 py-1 text-sm font-medium text-green-600"
                            >
                                BI
                            </span>
                        </div>

                        <!-- Gráfico -->
                        <div
                            class="flex h-28 items-end gap-2"
                            role="img"
                            aria-label="Representación de indicadores de desempeño logístico"
                        >
                            <div
                                v-for="(height, i) in bars"
                                :key="i"
                                class="flex-1 rounded-t"
                                :style="{
                                    height: `${height}%`,
                                    backgroundColor:
                                        i === bars.length - 1
                                            ? colors.naranjaClaro
                                            : colors.azulClaro,
                                }"
                            />
                        </div>

                        <!-- Datos -->
                        <div
                            class="mt-4 grid grid-cols-3 gap-3 border-t border-slate-100 pt-4"
                        >
                            <div>
                                <span class="text-xs text-slate-500">
                                    Rutas
                                </span>

                                <p
                                    class="text-sm font-medium text-slate-800"
                                >
                                    Analizadas
                                </p>
                            </div>

                            <div>
                                <span class="text-xs text-slate-500">
                                    Costos
                                </span>

                                <p
                                    class="text-sm font-medium text-slate-800"
                                >
                                    Operativos
                                </p>
                            </div>

                            <div>
                                <span class="text-xs text-slate-500">
                                    Tiempo
                                </span>

                                <p
                                    class="text-sm font-medium text-slate-800"
                                >
                                    Distribución
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- COMPONENTES DE LA SOLUCIÓN -->
            <section class="bg-slate-100">
                <div
                    class="mx-auto grid max-w-6xl grid-cols-2 gap-4 px-4 py-6 md:grid-cols-4"
                >
                    <div
                        v-for="stat in stats"
                        :key="stat.label"
                        class="text-center"
                    >
                        <div
                            class="text-2xl font-medium md:text-3xl"
                            :style="{
                                color: colors.azulClaro,
                            }"
                        >
                            {{ stat.value }}
                        </div>

                        <div
                            class="text-xs text-slate-600 md:text-sm"
                        >
                            {{ stat.label }}
                        </div>
                    </div>
                </div>
            </section>

            <!-- OBJETIVO DEL PROYECTO -->
            <section
                id="objetivo"
                class="mx-auto max-w-6xl px-4 py-12 md:py-16"
            >
                <div class="max-w-3xl">
                    <span
                        class="text-xs font-medium uppercase tracking-wider"
                        :style="{
                            color: colors.naranjaClaro,
                        }"
                    >
                        Propósito del proyecto
                    </span>

                    <h2
                        class="mt-2 text-2xl font-medium md:text-3xl"
                    >
                        Gestión operacional orientada al análisis
                        y optimización
                    </h2>

                    <p class="mt-4 text-sm leading-relaxed text-slate-600 md:text-base" >
                        Centraliza la información de tu operación logística y obtén una
                        visión clara del comportamiento de tus envíos, rutas y recursos.
                        Consulta los datos que necesitas para entender cómo se mueve tu
                        operación y detectar situaciones que requieren atención.
                    </p>

                    <p class="mt-4 text-sm leading-relaxed text-slate-600 md:text-base" >
                        Convierte los datos operacionales en información útil mediante
                        indicadores, análisis históricos y visualizaciones que permiten
                        explorar tiempos de distribución, costos, consumo de combustible,
                        utilización de vehículos y desempeño de las rutas.
                    </p>
                </div>
            </section>

            <!-- ARQUITECTURA -->
            <section
                id="arquitectura"
                class="bg-slate-50"
            >
                <div
                    class="mx-auto max-w-6xl px-4 py-12 md:py-16"
                >
                    <div class="max-w-3xl">
                        <span
                            class="text-xs font-medium uppercase tracking-wider"
                            :style="{
                                color: colors.naranjaClaro,
                            }"
                        >
                            Arquitectura de datos
                        </span>

                        <h2
                            class="mt-2 text-2xl font-medium md:text-3xl"
                        >
                            Del dato operacional al análisis
                        </h2>

                        <p
                            class="mt-4 text-sm leading-relaxed text-slate-600 md:text-base"
                        >
                            La arquitectura desacoplada permite cubrir
                            el ciclo de vida de los datos, desde su
                            recopilación y registro hasta su transformación,
                            almacenamiento y análisis.
                        </p>
                    </div>

                    <div class="mt-8 grid gap-4 md:grid-cols-3">
                        <!-- PostgreSQL / PostGIS -->
                        <article
                            class="rounded-xl border border-slate-200 bg-white p-5"
                        >
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-lg text-sm font-bold text-white"
                                :style="{
                                    backgroundColor:
                                        colors.azulClaro,
                                }"
                            >
                                DB
                            </div>

                            <h3 class="mt-4 text-base font-medium">
                                PostgreSQL + PostGIS
                            </h3>

                            <p
                                class="mt-2 text-sm leading-relaxed text-slate-600"
                            >
                                Base de datos operacional para gestionar
                                entidades y eventos logísticos, junto con
                                información geográfica relacionada con
                                puntos y rutas de distribución.
                            </p>
                        </article>

                        <!-- Neo4j -->
                        <article
                            class="rounded-xl border border-slate-200 bg-white p-5"
                        >
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-lg text-sm font-bold text-white"
                                :style="{
                                    backgroundColor:
                                        colors.naranjaClaro,
                                }"
                            >
                                G
                            </div>

                            <h3 class="mt-4 text-base font-medium">
                                Neo4j
                            </h3>

                            <p
                                class="mt-2 text-sm leading-relaxed text-slate-600"
                            >
                                Representación de la red de distribución
                                mediante nodos y conexiones para evaluar
                                caminos mínimos y posibles rutas
                                alternativas.
                            </p>
                        </article>

                        <!-- BI -->
                        <article
                            class="rounded-xl border border-slate-200 bg-white p-5"
                        >
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-lg text-sm font-bold text-white"
                                :style="{
                                    backgroundColor:
                                        colors.azulOscuro,
                                }"
                            >
                                BI
                            </div>

                            <h3 class="mt-4 text-base font-medium">
                                Inteligencia de Negocios
                            </h3>

                            <p
                                class="mt-2 text-sm leading-relaxed text-slate-600"
                            >
                                Consolidación y visualización de
                                indicadores para analizar clientes,
                                rutas, vehículos, tiempos y costos.
                            </p>
                        </article>
                    </div>

                    <!-- Flujo de datos -->
                    <div
                        class="mt-8 rounded-xl bg-white p-5 shadow-sm"
                    >
                        <div
                            class="flex flex-col items-center justify-between gap-4 text-center md:flex-row"
                        >
                            <div class="flex-1">
                                <span
                                    class="text-xs text-slate-500"
                                >
                                    01
                                </span>

                                <p
                                    class="mt-1 text-sm font-medium"
                                >
                                    Datos operacionales
                                </p>
                            </div>

                            <div
                                class="hidden text-slate-300 md:block"
                            >
                                →
                            </div>

                            <div class="flex-1">
                                <span
                                    class="text-xs text-slate-500"
                                >
                                    02
                                </span>

                                <p
                                    class="mt-1 text-sm font-medium"
                                >
                                    Integración y transformación
                                </p>
                            </div>

                            <div
                                class="hidden text-slate-300 md:block"
                            >
                                →
                            </div>

                            <div class="flex-1">
                                <span
                                    class="text-xs text-slate-500"
                                >
                                    03
                                </span>

                                <p
                                    class="mt-1 text-sm font-medium"
                                >
                                    Procesamiento
                                </p>
                            </div>

                            <div
                                class="hidden text-slate-300 md:block"
                            >
                                →
                            </div>

                            <div class="flex-1">
                                <span
                                    class="text-xs text-slate-500"
                                >
                                    04
                                </span>

                                <p
                                    class="mt-1 text-sm font-medium"
                                >
                                    Indicadores BI
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- ANALÍTICA -->
            <section
                id="analitica"
                class="mx-auto max-w-6xl px-4 py-12 md:py-16"
            >
                <div class="max-w-3xl">
                    <span
                        class="text-xs font-medium uppercase tracking-wider"
                        :style="{
                            color: colors.naranjaClaro,
                        }"
                    >
                        Inteligencia de Negocios
                    </span>

                    <h2
                        class="mt-2 text-2xl font-medium md:text-3xl"
                    >
                        {{ landingContent.featuresTitle }}
                    </h2>

                    <p
                        class="mt-4 text-sm leading-relaxed text-slate-600 md:text-base"
                    >
                        {{ landingContent.featuresDescription }}
                    </p>
                </div>

                <!-- Features -->
                <div class="mt-8 grid gap-4 md:grid-cols-3">
                    <article
                        v-for="feature in features"
                        :key="feature.title"
                        class="flex gap-3 rounded-xl border border-slate-200 p-4 md:flex-col"
                    >
                        <component
                            :is="feature.icon"
                            class="h-6 w-6 shrink-0"
                            :class="feature.color"
                            aria-hidden="true"
                        />

                        <div>
                            <h3
                                class="text-sm font-medium md:text-base"
                            >
                                {{ feature.title }}
                            </h3>

                            <p
                                class="mt-1 text-sm text-slate-600"
                            >
                                {{ feature.description }}
                            </p>
                        </div>
                    </article>
                </div>

                <!-- Indicadores -->
                <div
                    class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <div
                        class="rounded-xl border border-slate-200 p-4"
                    >
                        <span class="text-xs text-slate-500">
                            Clientes
                        </span>

                        <p class="mt-1 text-sm font-medium">
                            Cantidad de envíos
                        </p>

                        <p
                            class="mt-2 text-xs leading-relaxed text-slate-500"
                        >
                            Identificación de clientes con mayor volumen
                            de envíos.
                        </p>
                    </div>

                    <div
                        class="rounded-xl border border-slate-200 p-4"
                    >
                        <span class="text-xs text-slate-500">
                            Rutas
                        </span>

                        <p class="mt-1 text-sm font-medium">
                            Tiempos y retrasos
                        </p>

                        <p
                            class="mt-2 text-xs leading-relaxed text-slate-500"
                        >
                            Análisis de rutas con mayores tiempos
                            de distribución.
                        </p>
                    </div>

                    <div
                        class="rounded-xl border border-slate-200 p-4"
                    >
                        <span class="text-xs text-slate-500">
                            Costos
                        </span>

                        <p class="mt-1 text-sm font-medium">
                            Operación y combustible
                        </p>

                        <p
                            class="mt-2 text-xs leading-relaxed text-slate-500"
                        >
                            Evolución de costos operativos y consumo
                            de combustible.
                        </p>
                    </div>

                    <div
                        class="rounded-xl border border-slate-200 p-4"
                    >
                        <span class="text-xs text-slate-500">
                            Vehículos
                        </span>

                        <p class="mt-1 text-sm font-medium">
                            Nivel de utilización
                        </p>

                        <p
                            class="mt-2 text-xs leading-relaxed text-slate-500"
                        >
                            Análisis de utilización de los diferentes
                            tipos de vehículos.
                        </p>
                    </div>
                </div>
            </section>

            <!-- DASHBOARD -->
            <section class="bg-slate-50">
                <div
                    class="mx-auto grid max-w-6xl items-center gap-8 px-4 py-12 md:grid-cols-2 md:py-16"
                >
                    <div>
                        <span
                            class="text-xs font-medium uppercase tracking-wider"
                            :style="{
                                color: colors.naranjaClaro,
                            }"
                        >
                            Panel de control
                        </span>

                        <h2
                            class="mt-2 text-2xl font-medium md:text-3xl"
                        >
                            Una vista integrada de la operación
                        </h2>

                        <p
                            class="mt-4 text-sm leading-relaxed text-slate-600 md:text-base"
                        >
                            Los resultados del procesamiento y análisis
                            se presentan mediante un panel de control
                            interactivo que concentra los principales
                            indicadores de la operación logística.
                        </p>

                        <p
                            class="mt-4 text-sm leading-relaxed text-slate-600 md:text-base"
                        >
                            La información puede consultarse desde
                            diferentes perspectivas para analizar rutas,
                            vehículos, clientes, tiempos y costos.
                        </p>
                    </div>

                    <!-- Vista conceptual del dashboard -->
                    <div
                        class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"
                    >
                        <div class="grid grid-cols-2 gap-4">
                            <div
                                class="rounded-lg bg-slate-50 p-4"
                            >
                                <span
                                    class="text-xs text-slate-500"
                                >
                                    Tiempo
                                </span>

                                <p
                                    class="mt-1 text-sm font-medium"
                                >
                                    Distribución
                                </p>
                            </div>

                            <div
                                class="rounded-lg bg-slate-50 p-4"
                            >
                                <span
                                    class="text-xs text-slate-500"
                                >
                                    Costos
                                </span>

                                <p
                                    class="mt-1 text-sm font-medium"
                                >
                                    Operativos
                                </p>
                            </div>

                            <div
                                class="rounded-lg bg-slate-50 p-4"
                            >
                                <span
                                    class="text-xs text-slate-500"
                                >
                                    Rutas
                                </span>

                                <p
                                    class="mt-1 text-sm font-medium"
                                >
                                    Desempeño
                                </p>
                            </div>

                            <div
                                class="rounded-lg bg-slate-50 p-4"
                            >
                                <span
                                    class="text-xs text-slate-500"
                                >
                                    Vehículos
                                </span>

                                <p
                                    class="mt-1 text-sm font-medium"
                                >
                                    Utilización
                                </p>
                            </div>
                        </div>

                        <div
                            class="mt-4 rounded-lg p-4 text-white"
                            :style="{
                                backgroundColor:
                                    colors.azulOscuro,
                            }"
                        >
                            <span
                                class="text-xs text-slate-300"
                            >
                                Flujo de información
                            </span>

                            <p class="mt-1 text-sm font-medium">
                                Datos → Procesamiento → Indicadores → Análisis
                            </p>
                        </div>
                    </div>
                </div>
            </section>
 
            <section
                id="contacto"
                class="mx-auto max-w-6xl px-4 py-12"
            >
                <div class="rounded-xl px-4 py-8 text-center md:px-8 md:py-10" :style="{ backgroundColor: colors.azulClaro }" >
                    <h2 class="text-lg font-medium text-white md:text-2xl" >
                        {{ landingContent.contactTitle }}
                    </h2>

                    <p class="mx-auto mt-3 max-w-2xl text-sm leading-relaxed text-blue-100" >
                        {{ landingContent.contactDescription }}
                    </p>

                    <Link :href="login()" class="mt-5 inline-block rounded-lg bg-white px-6 py-2.5 text-sm font-medium hover:bg-slate-100"
                        :style="{ color: colors.azulClaro }" >
                        Acceder al sistema
                    </Link>
                </div>
            </section>
        </main>

        <!-- FOOTER -->
        <footer class="py-4 text-center text-xs text-slate-300" :style="{ backgroundColor: colors.azulOscuro }" >
            © {{ new Date().getFullYear() }} Prototipo Logistica
            · Sistema de Gestión Operacional y Analítica 
        </footer>
    </div>
</template>

