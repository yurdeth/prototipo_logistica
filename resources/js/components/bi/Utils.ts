// Tipos y utilidades compartidas por los componentes Bi*.

// Agrega transparencia a un color hexadecimal (fondo suave de badges).
export const soft = (hex: string, alpha = '1A') => `${hex}${alpha}`;

// Tarjeta de resumen (KPI).
export interface Stat {
    label: string;
    value: string | number;
    hint?: string;
    color?: string;
    accentColor?: string;
}

export interface BarRow {
    key: string;
    label: string;
    dot?: string;
    badge?: string;
    badgeColor?: string;
    detail?: string;
    accent?: string;
    accentColor?: string;
    width?: number;
    color?: string;
    mono?: boolean;
}

// Botón de filtro con contador.
export interface Chip {
    key: string;
    label: string;
    count: number;
    color: string;
}

// Filtro activo que se puede quitar.
export interface Activo {
    key: string;
    label: string;
}

// Columna de tabla. Si tiene `key`, es ordenable.
export interface Columna {
    key?: string;
    label: string;
    clase?: string;
}

// Dato del panel de detalle.
export interface Field {
    label: string;
    value: string | number;
    wide?: boolean;
    weight?: 'medium' | 'semibold';
}

// Elemento de una línea de tiempo. Con `marker` se dibuja un círculo numerado en lugar de un punto.
export interface TimelineItem {
    key: string | number;
    color: string;
    title: string;
    meta?: string;
    note?: string;
    marker?: string | number;
    tag?: string;
    tagColor?: string;
}
