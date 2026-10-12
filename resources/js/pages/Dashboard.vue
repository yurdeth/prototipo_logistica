<script setup lang="ts">
import { computed, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { colors } from '@/UI/Colors';
import { AlertTriangle, ChevronRight } from '@/UI/Icons';
import { dashboard } from '@/routes';
import {
    stats,
    monthlyShipments,
    flowSteps,
    statusShare,
    orders,
    orderStatus,
} from '@/config/dashboard';

// Componentes BI reutilizables (los mismos de Envíos y Rutas).
import StatCards from '@/components/bi/StatCards.vue';
import BiTabs from '@/components/bi/BiTabs.vue';
import BiPanelHead from '@/components/bi/BiPanelHead.vue';
import BiTable from '@/components/bi/BiTable.vue';
import BiTableRow from '@/components/bi/BiTableRow.vue';
import BiStatus from '@/components/bi/BiStatus.vue';
import type { Columna, Stat } from '@/components/bi/Utils';

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Dashboard', href: dashboard() }],
    },
});

// Pestaña activa: operación en tiempo real o analítica BI (Streamlit incrustado).
const tab = ref('operacion');
const tabs = [
    { key: 'operacion', label: 'Operación' },
    { key: 'bi', label: 'Analítica BI' },
] as const;

// Indicadores principales: el color solo aparece cuando un indicador empeoró.
const resumen = computed<Stat[]>(() =>
    stats.map((s) => ({
        label: s.label,
        value: s.value,
        hint: `${s.delta} vs. mes anterior`,
        color: s.isGood ? undefined : colors.rojo,
    })),
);

// Gráfico de envíos mensuales: el mes actual se resalta, los anteriores quedan suaves.
const maxShipment = Math.max(...monthlyShipments.map((d) => d.value));
const variacionMensual = '+12.4%';
const soft = (hex: string, alpha = '1A') => `${hex}${alpha}`;

// Tabla de pedidos recientes.
const columnasPedidos: Columna[] = [
    { label: 'Pedido' },
    { label: 'Destino' },
    { label: 'Cliente' },
    { label: 'Entrega' },
    { label: 'Estado' },
];
</script>

<template>
    <Head title="Dashboard" />

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
        <!-- Encabezado -->
        <header>
            <h1 class="text-2xl font-semibold tracking-tight text-foreground">
                Panel general
            </h1>
            <p class="mt-1 text-sm text-muted-foreground">
                Resumen de la operación logística y sus indicadores
            </p>
        </header>

        <!-- Cambia entre la operación y la analítica BI. -->
        <BiTabs v-model="tab" :tabs="tabs" label="Vistas del dashboard" />

        <template v-if="tab === 'operacion'">
            <!-- Lo más urgente va primero: es el único elemento con color de alerta. -->
            <Link
                href="/envios"
                class="flex items-center gap-3 rounded-xl border px-4 py-3 text-sm font-medium transition-opacity hover:opacity-90"
                :style="{
                    color: colors.rojo,
                    backgroundColor: soft(colors.rojo, '12'),
                    borderColor: soft(colors.rojo, '4D'),
                }"
            >
                <AlertTriangle class="h-5 w-5 shrink-0" aria-hidden="true" />
                <span class="flex-1"
                    >5 incidencias críticas requieren atención</span
                >
                <ChevronRight class="h-4 w-4" aria-hidden="true" />
            </Link>

            <!-- Indicadores principales. -->
            <StatCards :items="resumen" :cols="4" />

            <!-- Análisis principal: desempeño mensual y distribución por estado. -->
            <div class="grid gap-4 lg:grid-cols-3">
                <section
                    class="rounded-xl border border-sidebar-border/70 bg-card p-4 shadow-sm sm:p-5 lg:col-span-2"
                    aria-label="Desempeño logístico"
                >
                    <BiPanelHead
                        title="Desempeño logístico"
                        description="Envíos por mes, de enero a octubre"
                    >
                        <p class="text-sm text-muted-foreground">
                            <span class="font-semibold text-foreground">{{
                                variacionMensual
                            }}</span>
                            vs. mes anterior
                        </p>
                    </BiPanelHead>

                    <!-- Barras proporcionales al volumen mensual de envíos. -->
                    <div
                        class="flex h-48 items-end justify-between gap-1.5 sm:gap-2"
                        role="img"
                        aria-label="Envíos por mes, de enero a octubre"
                    >
                        <div
                            v-for="(data, i) in monthlyShipments"
                            :key="data.month"
                            class="flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-2"
                            :title="`${data.month}: ${data.value} envíos`"
                        >
                            <span
                                class="text-xs font-medium text-muted-foreground"
                                >{{ data.value }}</span
                            >
                            <div
                                class="w-full max-w-8 rounded-t-md"
                                :style="{
                                    height: `${(data.value / maxShipment) * 80}%`,
                                    backgroundColor:
                                        i === monthlyShipments.length - 1
                                            ? colors.azulClaro
                                            : soft(colors.azulClaro, '59'),
                                }"
                            />
                            <span class="text-xs text-muted-foreground">{{
                                data.month
                            }}</span>
                        </div>
                    </div>
                </section>

                <section
                    class="rounded-xl border border-sidebar-border/70 bg-card p-4 shadow-sm sm:p-5"
                    aria-label="Estado de los envíos"
                >
                    <BiPanelHead
                        title="Estado de los envíos"
                        description="Distribución actual por estado."
                    />

                    <!-- Barra porcentual. -->
                    <div
                        class="flex h-3 overflow-hidden rounded-full bg-muted"
                        role="img"
                        aria-label="Distribución de envíos por estado"
                    >
                        <div
                            v-for="share in statusShare"
                            :key="share.label"
                            :style="{
                                width: `${share.percent}%`,
                                backgroundColor: share.color,
                            }"
                        />
                    </div>

                    <!-- Detalle de porcentajes. -->
                    <ul class="mt-4 flex flex-col gap-1">
                        <li
                            v-for="share in statusShare"
                            :key="share.label"
                            class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm"
                        >
                            <span
                                class="h-2.5 w-2.5 shrink-0 rounded-full"
                                :style="{ backgroundColor: share.color }"
                            />
                            <span class="flex-1 font-medium text-foreground">{{
                                share.label
                            }}</span>
                            <span class="text-muted-foreground"
                                >{{ share.percent }}%</span
                            >
                        </li>
                    </ul>
                </section>
            </div>

            <!-- Pedidos recientes. -->
            <section
                class="overflow-hidden rounded-xl border border-sidebar-border/70 bg-card shadow-sm"
                aria-label="Pedidos recientes"
            >
                <div
                    class="flex items-center justify-between border-b border-sidebar-border/70 bg-muted/20 px-5 py-4"
                >
                    <h2 class="text-sm font-semibold text-foreground">
                        Pedidos recientes
                    </h2>
                    <Link
                        href="/envios"
                        class="text-sm font-medium text-blue-700 hover:underline dark:text-blue-300"
                        >Ver todos</Link
                    >
                </div>

                <BiTable
                    :columns="columnasPedidos"
                    :vacio="!orders.length"
                    empty-title="No hay pedidos recientes"
                    empty-hint="Los nuevos pedidos aparecerán aquí."
                >
                    <BiTableRow
                        v-for="order in orders"
                        :key="order.id"
                        :selectable="false"
                    >
                        <td
                            class="px-5 py-4 font-mono text-sm font-medium text-foreground"
                        >
                            {{ order.id }}
                        </td>
                        <td class="px-5 py-4 text-foreground">
                            {{ order.city }}
                        </td>
                        <td class="px-5 py-4 text-muted-foreground">
                            {{ order.client }}
                        </td>
                        <td
                            class="px-5 py-4 whitespace-nowrap text-muted-foreground"
                        >
                            {{ order.eta }}
                        </td>
                        <td class="px-5 py-4">
                            <BiStatus
                                class="text-foreground"
                                :color="orderStatus[order.status].color"
                                :label="orderStatus[order.status].label"
                            />
                        </td>
                    </BiTableRow>
                </BiTable>
            </section>

            <!-- Flujo de información: es contexto, por eso va al final y sin color. -->
            <section
                class="rounded-xl border border-sidebar-border/70 bg-card p-4 shadow-sm sm:p-5"
                aria-label="Flujo de información"
            >
                <BiPanelHead
                    title="Flujo de información"
                    description="Datos → Procesamiento → Indicadores → Análisis"
                />

                <ol class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <li
                        v-for="(step, index) in flowSteps"
                        :key="step"
                        class="flex items-center gap-3 rounded-lg border border-sidebar-border/70 p-3 text-sm text-foreground"
                    >
                        <span
                            class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md bg-muted text-xs font-semibold text-muted-foreground"
                            >{{ index + 1 }}</span
                        >
                        <span>{{ step }}</span>
                    </li>
                </ol>
            </section>
        </template>

        <!-- Analítica BI (Streamlit incrustado): más adelante se colocara aquí un psoible iframe. -->
    </div>
</template>
