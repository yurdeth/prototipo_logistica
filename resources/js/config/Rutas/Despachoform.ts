// resources/js/config/despachoForm.ts
// Tipos, reglas y opciones de demostración del formulario de despachos.
// Las opciones de demo solo se usan mientras Laravel no envíe las reales.
import type { Prioridad } from '@/config/Envios/envios';

export interface OpcionSimple {
    id: number;
    nombre: string;
}

export interface Vehiculo {
    id: number;
    placa: string;
    tipo: string;
    capacidad_kg: number | null; // si es null, no se valida la carga
    costo_km: number | null; // costo estimado por km, usado para sugerir el costo del despacho
}

// Envío que aún no está asignado a ningún despacho
export interface EnvioDisponible {
    id_envio: number;
    codigo_guia: string;
    destino: string;
    peso_kg: number;
    prioridad: Prioridad;
    fecha_comprometida: string; // ISO
    sucursal_origen_id: number;
}

// Despachos ya existentes, para detectar conductor/vehículo ocupado ese día
export interface Ocupacion {
    fecha: string; // YYYY-MM-DD
    conductor_id: number;
    vehiculo_id: number;
}

/* ---------- Reglas (ajustables) ---------- */
export const HORA_SALIDA_DEFECTO = '08:00';
export const INTERVALO_MIN_DEFECTO = 40; // minutos entre paradas
export const MARGEN_JUSTO_HORAS = 2; // si la entrega llega con menos margen, se avisa como "justa"

/* ---------- Datos de demostración ---------- */
const pad = (n: number) => String(n).padStart(2, '0');
export const fechaLocal = (d: Date) =>
    `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

export const sucursalesDemo: OpcionSimple[] = [
    { id: 1, nombre: 'Sucursal Central' },
    { id: 2, nombre: 'Sucursal Oriente' },
    { id: 3, nombre: 'Sucursal Occidente' },
];

export const conductoresDemo: OpcionSimple[] = [
    { id: 1, nombre: 'Luis Martínez' },
    { id: 2, nombre: 'Ana Rivas' },
    { id: 3, nombre: 'Carlos Pérez' },
    { id: 4, nombre: 'Marta Gómez' },
    { id: 5, nombre: 'José Hernández' },
    { id: 6, nombre: 'Sofía Castillo' },
];

export const vehiculosDemo: Vehiculo[] = [
    {
        id: 1,
        placa: 'P-123-456',
        tipo: 'Furgoneta',
        capacidad_kg: 350,
        costo_km: 0.48,
    },
    {
        id: 2,
        placa: 'P-234-567',
        tipo: 'Pickup',
        capacidad_kg: 600,
        costo_km: 0.52,
    },
    {
        id: 3,
        placa: 'P-345-678',
        tipo: 'Furgoneta',
        capacidad_kg: 350,
        costo_km: 0.61,
    },
    {
        id: 4,
        placa: 'P-456-789',
        tipo: 'Camión',
        capacidad_kg: 2000,
        costo_km: 0.9,
    },
    {
        id: 5,
        placa: 'M-567-890',
        tipo: 'Motocicleta',
        capacidad_kg: 40,
        costo_km: 0.22,
    },
];

export function generarEnviosDisponiblesDemo(): EnvioDisponible[] {
    const H = 3600 * 1000;
    const now = Date.now();
    const filas: [string, number, Prioridad, number][] = [
        ['San Salvador', 12.5, 'URGENTE', 6],
        ['Santa Tecla', 4.2, 'NORMAL', 20],
        ['Soyapango', 28.0, 'NORMAL', 30],
        ['San Salvador', 9.8, 'URGENTE', 9],
        ['Santa Tecla', 36.5, 'BAJA', 70],
        ['Soyapango', 2.1, 'NORMAL', 28],
        ['San Miguel', 15.0, 'NORMAL', 44],
        ['San Salvador', 6.3, 'NORMAL', 22],
        ['Santa Ana', 41.0, 'BAJA', 90],
        ['Santa Tecla', 7.7, 'URGENTE', 12],
        ['Soyapango', 18.2, 'NORMAL', 36],
        ['San Salvador', 3.4, 'BAJA', 60],
    ];
    return filas.map(([destino, peso, prioridad, horas], i) => ({
        id_envio: 200 + i,
        codigo_guia: `PK-${10600 + i}`,
        destino,
        peso_kg: peso,
        prioridad,
        fecha_comprometida: new Date(now + horas * H).toISOString(),
        sucursal_origen_id: (i % 3) + 1,
    }));
}

export function generarOcupacionDemo(): Ocupacion[] {
    const hoy = new Date();
    const manana = new Date(hoy.getTime() + 24 * 3600 * 1000);
    return [
        { fecha: fechaLocal(hoy), conductor_id: 1, vehiculo_id: 3 },
        { fecha: fechaLocal(manana), conductor_id: 2, vehiculo_id: 1 },
    ];
}
