<script setup lang="ts">
import { ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import { colors } from '@/UI/Colors';
import {
    AlertTriangle,
    Plus,
    Route,
    LayoutDashboard,
    BarChart3,
} from '@/UI/Icons';
import { dashboard } from '@/routes';
import {
    stats,
    monthlyShipments,
    flowSteps,
    statusShare,
    orders,
    orderStatus,
} from '@/config/dashboard';

// Pestaña activa: operación en tiempo real o analítica BI (Streamlit incrustado)
type Tab = 'operacion' | 'bi';
const tab = ref<Tab>('operacion');

const tabs: { key: Tab; label: string; icon: typeof LayoutDashboard }[] = [
    { key: 'operacion', label: 'Operación', icon: LayoutDashboard },
    { key: 'bi', label: 'Analítica BI', icon: BarChart3 },
];

// definiendo el layout de la aplicación y los breadcrumbs que aparecerán en la navegación.
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

// CÁLCULO DEL VALOR MÁXIMO DEL GRÁFICO
const maxShipment = Math.max(...monthlyShipments.map((d) => d.value));

// FUNCIÓN PARA GENERAR COLORES CON TRANSPARENCIA
const soft = (hex: string, alpha = '1A') => `${hex}${alpha}`;
</script>

<template>
    <Head title="Dashboard" />

    <!-- Botones de navegación -->
    <div class="mt-4 ml-6">
        <div class="flex gap-2" role="tablist">
            <button
                v-for="item in tabs"
                :key="item.key"
                type="button"
                role="tab"
                :aria-selected="tab === item.key"
                class="inline-flex items-center gap-1.5 rounded-lg border px-3.5 py-2 text-sm font-medium transition-colors"
                :class="
                    tab === item.key
                        ? 'text-white'
                        : 'border-sidebar-border/70 bg-card text-muted-foreground hover:text-foreground'
                "
                :style="
                    tab === item.key
                        ? {
                            backgroundColor: colors.azulClaro,
                            borderColor: colors.azulClaro,
                        }
                        : undefined
                "
                @click="tab = item.key"
            >
                <component :is="item.icon" class="h-4 w-4" aria-hidden="true" />
                {{ item.label }}
            </button>
        </div>
    </div>

    <div class="flex h-full flex-1 flex-col gap-4 overflow-x-auto p-4">
        <!-- Alerta de incidencias -->
        <a
            href="#"
            class="flex items-center gap-3 rounded-xl border px-4 py-3 text-sm"
            :style="{
                color: colors.rojo,
                backgroundColor: soft(colors.rojo, '12'),
                borderColor: soft(colors.rojo, '4D'),
            }"
        >
            <AlertTriangle class="h-5 w-5 shrink-0" aria-hidden="true" />

            <span class="flex-1">
                5 incidencias críticas requieren atención
            </span>

            <ChevronRight class="h-4 w-4" aria-hidden="true" />
        </a>

        <!-- Indicadores principales -->
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
                    :style="{
                        color: stat.isGood ? colors.verde : colors.rojo,
                    }"
                >
                    {{ stat.delta }} vs. mes anterior
                </p>
            </div>
        </div>

        <!-- Dos columnas: desempeño y flujo -->
        <div class="grid gap-4 lg:grid-cols-2">
            <!-- Desempeño logístico -->
            <div class="rounded-xl border border-sidebar-border/70 bg-card p-5">
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
                        class="rounded-lg px-3 py-1 text-sm font-medium"
                        :style="{
                            color: colors.verde,
                            backgroundColor: soft(colors.verde),
                        }"
                    >
                        +12.4%
                    </span>
                </div>

                <!-- Gráfico de envíos mensuales -->
                <div
                    class="mt-6 flex h-40 items-end justify-between gap-2"
                    role="img"
                    aria-label="Envíos por mes, de enero a octubre"
                >
                    <div
                        v-for="(data, i) in monthlyShipments"
                        :key="data.month"
                        class="flex h-full flex-1 flex-col items-center justify-end gap-2"
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
                class="flex flex-col rounded-xl border border-sidebar-border/70 bg-card p-5"
            >
                <span class="text-xs text-muted-foreground">
                    Flujo de información
                </span>

                <p class="mt-1 text-sm font-medium text-foreground">
                    Datos → Procesamiento → Indicadores → Análisis
                </p>

                <div class="mt-auto grid gap-3 pt-6">
                    <div
                        v-for="(step, index) in flowSteps"
                        :key="step"
                        class="flex items-center gap-3 rounded-lg border border-sidebar-border/70 px-3 py-3 text-xs text-foreground"
                    >
                        <span
                            class="flex h-6 w-6 shrink-0 items-center justify-center rounded-md text-xs font-semibold"
                            :style="{
                                color: index === flowSteps.length - 1
                                    ? colors.naranjaClaro
                                    : colors.azulClaro,
                                backgroundColor: soft(
                                    index === flowSteps.length - 1
                                        ? colors.naranjaClaro
                                        : colors.azulClaro
                                ),
                            }"
                        >
                            {{ index + 1 }}
                        </span>

                        <span>{{ step }}</span>
                    </div>
                </div>
            </div>

        </div>

        <!-- Dos columnas: pedidos y estado -->
        <div class="grid gap-4 lg:grid-cols-2">
            <!-- Pedidos recientes -->
            <div class="rounded-xl border border-sidebar-border/70 bg-card">
                <div class="flex items-center justify-between px-5 pt-5">
                    <h2 class="text-base font-medium text-foreground">
                        Pedidos recientes
                    </h2>

                    <a
                        href="#"
                        class="text-sm hover:underline"
                        :style="{ color: colors.azulClaro }"
                    >
                        Ver todos
                    </a>
                </div>

                <div class="overflow-x-auto">
                    <table class="mt-3 w-full text-left text-sm">
                        <thead>
                            <tr
                                class="border-b border-sidebar-border/70 font-mono text-[10px] tracking-wider text-muted-foreground uppercase"
                            >
                                <th class="px-5 py-2 font-normal">Pedido</th>
                                <th class="px-5 py-2 font-normal">Destino</th>
                                <th
                                    class="hidden px-5 py-2 font-normal sm:table-cell"
                                >
                                    Cliente
                                </th>
                                <th
                                    class="hidden px-5 py-2 font-normal md:table-cell"
                                >
                                    Entrega
                                </th>
                                <th class="px-5 py-2 font-normal">Estado</th>
                            </tr>
                        </thead>

                        <tbody>
                            <tr
                                v-for="order in orders"
                                :key="order.id"
                                class="border-b border-sidebar-border/40 last:border-b-0"
                            >
                                <td
                                    class="px-5 py-3 font-mono text-xs text-foreground"
                                >
                                    {{ order.id }}
                                </td>

                                <td class="px-5 py-3 text-foreground">
                                    {{ order.city }}
                                </td>

                                <td
                                    class="hidden px-5 py-3 text-muted-foreground sm:table-cell"
                                >
                                    {{ order.client }}
                                </td>

                                <td
                                    class="hidden px-5 py-3 text-muted-foreground md:table-cell"
                                >
                                    {{ order.eta }}
                                </td>

                                <td class="px-5 py-3">
                                    <!-- Estado dinámico del pedido -->
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-xs font-medium"
                                        :style="{
                                            color: orderStatus[order.status]
                                                .color,
                                            backgroundColor: soft(
                                                orderStatus[order.status].color,
                                            ),
                                        }"
                                    >
                                        <component
                                            :is="orderStatus[order.status].icon"
                                            class="h-3 w-3"
                                            aria-hidden="true"
                                        />

                                        {{ orderStatus[order.status].label }}
                                    </span>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Estado de los envíos -->
            <div class="rounded-xl border border-sidebar-border/70 bg-card p-5">
                <h2 class="mb-4 text-base font-medium text-foreground">
                    Estado de los envíos
                </h2>

                <!-- Barra porcentual -->
                <div
                    class="flex h-3 overflow-hidden rounded-full"
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

                <!-- Detalle de porcentajes -->
                <ul class="mt-4 space-y-2.5">
                    <li
                        v-for="share in statusShare"
                        :key="share.label"
                        class="flex items-center gap-2 text-sm"
                    >
                        <span
                            class="h-2.5 w-2.5 rounded-full"
                            :style="{ backgroundColor: share.color }"
                        />

                        <span class="flex-1 text-foreground">
                            {{ share.label }}
                        </span>

                        <span class="font-mono text-xs text-muted-foreground">
                            {{ share.percent }}%
                        </span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>
