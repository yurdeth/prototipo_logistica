import type { Component } from 'vue';

import {
    MapPin,
    Route,
    BarChart3,
} from '@/UI/Icons';

interface Feature {
    title: string;
    description: string;
    icon: Component;
    color: string;
}

interface Stat {
    value: string;
    label: string;
}

export const landingContent = {
    badge: 'Inteligencia para tu operación logística',

    title: 'Convierte tus datos logísticos en decisiones inteligentes',

    description:
        'Visualiza rutas, envíos, tiempos, costos y vehículos desde un solo lugar. Analiza el comportamiento de tu operación y descubre oportunidades para mejorar la distribución.',

    primaryButton: 'Explorar dashboard',

    secondaryButton: 'Conocer la solución',

    featuresTitle:
        'Todo lo que necesitas para entender tu operación',

    featuresDescription:
        'Una visión integrada de tus rutas, envíos, vehículos, costos y principales indicadores de desempeño.',

    contactTitle:
        'Una operación más visible. Decisiones más claras.',

    contactDescription:
        'Explora los datos de tu operación logística desde diferentes perspectivas y encuentra información que te ayude a comprender su comportamiento.',
};


export const features: Feature[] = [
    {
        title: 'Visualiza tus rutas',
        description:
            'Consulta recorridos, tiempos y comportamiento de las rutas de distribución.',
        icon: Route,
        color: 'text-accent',
    },
    {
        title: 'Entiende tus envíos',
        description:
            'Analiza el volumen de envíos y descubre cómo se comporta la operación.',
        icon: MapPin,
        color: 'text-action',
    },
    {
        title: 'Mide el desempeño',
        description:
            'Consulta indicadores de tiempos, costos, combustible y utilización de vehículos.',
        icon: BarChart3,
        color: 'text-cyan-600',
    },
];

export const stats: Stat[] = [
    {
        value: 'Rutas',
        label: 'análisis de recorridos',
    },
    {
        value: 'Envíos',
        label: 'seguimiento operacional',
    },
    {
        value: 'Costos',
        label: 'control operativo',
    },
    {
        value: 'BI',
        label: 'indicadores de negocio',
    },
];


export const bars = [45, 58, 52, 70, 64, 82, 94];
