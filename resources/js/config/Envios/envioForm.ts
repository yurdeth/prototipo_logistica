// resources/js/config/envioForm.ts
// Tipos, regla de compromiso por prioridad y opciones de demostración del formulario.
// Estas opciones son de demo solo se usaran mientras Laravel no envíe las reales.
import type { Prioridad } from '@/config/Envios/envios';

export interface Opcion {
    id: number;
    nombre: string;
}

export interface ClienteOpcion extends Opcion {
    tipo_cliente: 'PERSONA' | 'EMPRESA';
}

// Horas sugeridas para el compromiso de entrega según la prioridad
export const horasCompromiso: Record<Prioridad, number> = {
    URGENTE: 24,
    NORMAL: 72,
    BAJA: 120,
};

// Peso (kg) a partir del cual se avisa que se requiere vehículo con capacidad adecuada
export const PESO_CARGA_PESADA = 30;

export const clientesDemo: ClienteOpcion[] = [
    { id: 1, nombre: 'Distribuidora Andina', tipo_cliente: 'EMPRESA' },
    { id: 2, nombre: 'Comercial Sur', tipo_cliente: 'EMPRESA' },
    { id: 3, nombre: 'Importadora Norte', tipo_cliente: 'EMPRESA' },
    { id: 4, nombre: 'Farmacias Vida', tipo_cliente: 'EMPRESA' },
    { id: 5, nombre: 'Ferretería Central', tipo_cliente: 'EMPRESA' },
    { id: 6, nombre: 'María López', tipo_cliente: 'PERSONA' },
    { id: 7, nombre: 'Carlos Pérez', tipo_cliente: 'PERSONA' },
    { id: 8, nombre: 'Ana Rivas', tipo_cliente: 'PERSONA' },
];

export const sucursalesDemo: Opcion[] = [
    { id: 1, nombre: 'Sucursal Central' },
    { id: 2, nombre: 'Sucursal Oriente' },
    { id: 3, nombre: 'Sucursal Occidente' },
];

export const territoriosDemo: Opcion[] = [
    { id: 1, nombre: 'San Salvador' },
    { id: 2, nombre: 'Santa Tecla' },
    { id: 3, nombre: 'Soyapango' },
    { id: 4, nombre: 'San Miguel' },
    { id: 5, nombre: 'Santa Ana' },
    { id: 6, nombre: 'Usulután' },
    { id: 7, nombre: 'Sonsonate' },
    { id: 8, nombre: 'La Unión' },
];
