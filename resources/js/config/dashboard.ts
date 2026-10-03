// resources/js/config/dashboard.ts

import type { Component } from 'vue';
import { colors } from '@/UI/Colors';
import {
    Truck,
    Route,
    MapPin,
    BarChart3,
    CheckCircle2,
    AlertTriangle,
    Clock,
} from '@/UI/Icons';

/* ---------- Indicadores clave ---------- */
export interface Stat {
    label: string;
    value: string;
    delta: string;
    isGood: boolean;
    icon: Component;
}

export const stats: Stat[] = [
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

/* ---------- Envíos por mes ---------- */
export interface MonthlyShipment {
    month: string;
    value: number;
}

export const monthlyShipments: MonthlyShipment[] = [
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

/* ---------- Flujo de información ---------- */
export const flowSteps: string[] = [
    'Registro de datos operacionales',
    'Integración y transformación',
    'Indicadores de inteligencia de negocios',
];

/* ---------- Distribución por estado ---------- */
export interface StatusShare {
    label: string;
    percent: number;
    color: string;
}

export const statusShare: StatusShare[] = [
    { label: 'Entregado', percent: 62, color: colors.verde },
    { label: 'En ruta', percent: 24, color: colors.amarillo },
    { label: 'Pendiente', percent: 9, color: colors.cian },
    { label: 'Retrasado', percent: 5, color: colors.rojo },
];

/* ---------- Pedidos recientes ---------- */
export type OrderStatus = 'delivered' | 'transit' | 'delayed' | 'pending';

export const orderStatus: Record<
    OrderStatus,
    { label: string; color: string; icon: Component }
> = {
    delivered: {
        label: 'Entregado',
        color: colors.verde,
        icon: CheckCircle2,
    },
    transit: {
        label: 'En ruta',
        color: colors.amarillo,
        icon: Truck,
    },
    delayed: {
        label: 'Retrasado',
        color: colors.rojo,
        icon: AlertTriangle,
    },
    pending: {
        label: 'Pendiente',
        color: colors.cian,
        icon: Clock,
    },
};

export interface Order {
    id: string;
    city: string;
    client: string;
    status: OrderStatus;
    eta: string;
}

export const orders: Order[] = [
    {
        id: 'PED-4821',
        city: 'San Salvador',
        client: 'Distribuidora Cuscatlán',
        status: 'delivered',
        eta: 'Hoy 14:02',
    },
    {
        id: 'PED-4822',
        city: 'Santa Ana',
        client: 'Comercial Occidental',
        status: 'transit',
        eta: 'Hoy 18:30',
    },
    {
        id: 'PED-4823',
        city: 'San Miguel',
        client: 'Distribuidora Oriental',
        status: 'delayed',
        eta: 'Ayer 20:00',
    },
    {
        id: 'PED-4824',
        city: 'Usulután',
        client: 'Logística del Pacífico',
        status: 'pending',
        eta: 'Mañana 09:00',
    },
    {
        id: 'PED-4825',
        city: 'La Unión',
        client: 'Grupo Cuscatlán',
        status: 'delivered',
        eta: 'Hoy 11:45',
    },
];
