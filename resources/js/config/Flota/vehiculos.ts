// resources/js/config/Flota/vehiculos.ts
// Tipos, colores por estado y datos de demostración de la flota de vehículos.
// Estos datos de demo solo se usan mientras Laravel no envíe los reales.
import { colors } from '@/UI/Colors';

export interface TipoVehiculo {
    id_tipo_vehiculo: number;
    nombre: string;
    marca: string;
    capacidad_kg: string;
    capacidad_m3: string | null;
}

export interface EstadoVehiculo {
    id_estado: number;
    nombre_estado: string;
}

export interface SucursalVehiculo {
    id_sucursal: number;
    nombre_sucursal: string;
}

export interface Vehiculo {
    id_vehiculo: number;
    placa: string;
    marca: string | null;
    modelo: string | null;
    anio: number | null;
    consumo_km_gal: string | null;
    tipo: TipoVehiculo;
    estado: EstadoVehiculo;
    sucursal: SucursalVehiculo;
}

/* ───────── Placas: formato nacional (El Salvador) ─────────
 * La placa es la letra(s) de categoría del vehículo seguida de hasta 6
 * caracteres alfanuméricos; la cantidad exacta varía según la emisión
 * (formatos antiguos cortos y mixtos como "P 4 57A" -> "P457A"):
 *   P  Particular            C  Carga
 *   M  Motocicleta           A  Transporte público
 *   N  No remunerado         MB Microbús / AB Transporte colectivo
 *   RE Remolques             CD Cuerpo diplomático
 *   CC Cuerpo consular
 * Ejemplos válidos: P123456, C345678, M1234, AB12345, P457A.
 * El servidor valida la misma regla en StoreVehiculoRequest::FORMATO_PLACA.
 */
export const FORMATO_PLACA = /^(P|C|M|A|N|MB|AB|RE|CD|CC)[A-Z0-9]{1,6}$/;

// Mayúsculas, sin espacios ni guiones (misma normalización que aplica el servidor).
export const normalizarPlaca = (valor: string) =>
    valor.toUpperCase().replace(/[\s-]/g, '');

// Estados operativos del parque y su color en los indicadores.
export const estadoInfo: Record<string, { label: string; color: string }> = {
    DISPONIBLE: { label: 'Disponible', color: colors.verde },
    'EN RUTA': { label: 'En ruta', color: colors.amarillo },
    MANTENIMIENTO: { label: 'En mantenimiento', color: colors.rojo },
    INACTIVO: { label: 'Inactivo', color: colors.grisMedio },
};

export const colorDeEstado = (nombre: string) =>
    estadoInfo[nombre]?.color ?? colors.grisMedio;

export const labelDeEstado = (nombre: string) =>
    estadoInfo[nombre]?.label ?? nombre;

// Flota de demostración para el diseño de la vista.
export const vehiculosDemo: Vehiculo[] = [
    {
        id_vehiculo: 1,
        placa: 'M123456',
        marca: 'Toyota',
        modelo: 'Hilux',
        anio: 2023,
        consumo_km_gal: '35.50',
        tipo: {
            id_tipo_vehiculo: 1,
            nombre: 'Motocicleta',
            marca: 'Honda',
            capacidad_kg: '50.00',
            capacidad_m3: null,
        },
        estado: { id_estado: 1, nombre_estado: 'DISPONIBLE' },
        sucursal: { id_sucursal: 1, nombre_sucursal: 'Bodega Base SCZ' },
    },
    {
        id_vehiculo: 2,
        placa: 'M234567',
        marca: 'Nissan',
        modelo: 'Frontier',
        anio: 2022,
        consumo_km_gal: '30.00',
        tipo: {
            id_tipo_vehiculo: 1,
            nombre: 'Motocicleta',
            marca: 'Honda',
            capacidad_kg: '50.00',
            capacidad_m3: null,
        },
        estado: { id_estado: 2, nombre_estado: 'EN RUTA' },
        sucursal: { id_sucursal: 1, nombre_sucursal: 'Bodega Base SCZ' },
    },
    {
        id_vehiculo: 3,
        placa: 'C345678',
        marca: 'Mitsubishi',
        modelo: 'Canter',
        anio: 2021,
        consumo_km_gal: '8.20',
        tipo: {
            id_tipo_vehiculo: 3,
            nombre: 'Camion Ligero',
            marca: 'Isuzu',
            capacidad_kg: '3500.00',
            capacidad_m3: '12.00',
        },
        estado: { id_estado: 3, nombre_estado: 'MANTENIMIENTO' },
        sucursal: { id_sucursal: 1, nombre_sucursal: 'Bodega Base SCZ' },
    },
];

// Opciones de demo para el formulario de registro (mismos ids que el seeder).
export const tiposVehiculoDemo: TipoVehiculo[] = [
    {
        id_tipo_vehiculo: 1,
        nombre: 'Motocicleta',
        marca: 'Honda',
        capacidad_kg: '50.00',
        capacidad_m3: null,
    },
    {
        id_tipo_vehiculo: 2,
        nombre: 'Van',
        marca: 'Toyota',
        capacidad_kg: '800.00',
        capacidad_m3: '6.00',
    },
    {
        id_tipo_vehiculo: 3,
        nombre: 'Camion Ligero',
        marca: 'Isuzu',
        capacidad_kg: '3500.00',
        capacidad_m3: '12.00',
    },
    {
        id_tipo_vehiculo: 4,
        nombre: 'Camion Pesado',
        marca: 'Volvo',
        capacidad_kg: '18000.00',
        capacidad_m3: '45.00',
    },
];

export const estadosVehiculoDemo: EstadoVehiculo[] = [
    { id_estado: 1, nombre_estado: 'DISPONIBLE' },
    { id_estado: 2, nombre_estado: 'EN RUTA' },
    { id_estado: 3, nombre_estado: 'MANTENIMIENTO' },
    { id_estado: 4, nombre_estado: 'INACTIVO' },
];

export const sucursalesVehiculoDemo: SucursalVehiculo[] = [
    { id_sucursal: 1, nombre_sucursal: 'Bodega Base SCZ' },
];

/* ───────── Marcas y modelos (catálogo fijo) ───────── */

export interface MarcaVehiculoCatalogo {
    id_marca: number;
    nombre: string;
    activa: boolean;
}

export interface ModeloVehiculoCatalogo {
    id_modelo: number;
    marca_vehiculo_id: number;
    tipo_vehiculo_id: number | null;
    nombre: string;
    activa: boolean;
}

// Marcas fijas del catálogo; también se sembraron en flota.marca_vehiculo.
// Chevrolet/Kia/Mitsubishi/Nissan/Toyota: vehículos panel/carga.
// Honda/Suzuki/Yamaha: motocicletas.
export const marcasVehiculoDemo: MarcaVehiculoCatalogo[] = [
    { id_marca: 1, nombre: 'Chevrolet', activa: true },
    { id_marca: 2, nombre: 'Honda', activa: true },
    { id_marca: 3, nombre: 'Kia', activa: true },
    { id_marca: 4, nombre: 'Mitsubishi', activa: true },
    { id_marca: 5, nombre: 'Nissan', activa: true },
    { id_marca: 6, nombre: 'Suzuki', activa: true },
    { id_marca: 7, nombre: 'Toyota', activa: true },
    { id_marca: 8, nombre: 'Yamaha', activa: true },
];

// Solo modelos panel/carga (van y camiones) y motocicletas; sin pickups ni sedanes.
// Cada modelo declara su tipo de vehículo, igual que el seeder.
export const modelosVehiculoDemo: ModeloVehiculoCatalogo[] = [
    {
        id_modelo: 1,
        marca_vehiculo_id: 1,
        tipo_vehiculo_id: 3,
        nombre: 'NHR',
        activa: true,
    },
    {
        id_modelo: 2,
        marca_vehiculo_id: 1,
        tipo_vehiculo_id: 3,
        nombre: 'NPR',
        activa: true,
    },
    {
        id_modelo: 3,
        marca_vehiculo_id: 2,
        tipo_vehiculo_id: 1,
        nombre: 'Boxer CT100',
        activa: true,
    },
    {
        id_modelo: 4,
        marca_vehiculo_id: 2,
        tipo_vehiculo_id: 1,
        nombre: 'CB125',
        activa: true,
    },
    {
        id_modelo: 5,
        marca_vehiculo_id: 3,
        tipo_vehiculo_id: 2,
        nombre: 'K2500',
        activa: true,
    },
    {
        id_modelo: 6,
        marca_vehiculo_id: 4,
        tipo_vehiculo_id: 3,
        nombre: 'Canter',
        activa: true,
    },
    {
        id_modelo: 7,
        marca_vehiculo_id: 5,
        tipo_vehiculo_id: 2,
        nombre: 'Urvan',
        activa: true,
    },
    {
        id_modelo: 8,
        marca_vehiculo_id: 6,
        tipo_vehiculo_id: 1,
        nombre: 'AX100',
        activa: true,
    },
    {
        id_modelo: 9,
        marca_vehiculo_id: 7,
        tipo_vehiculo_id: 2,
        nombre: 'Hiace',
        activa: true,
    },
    {
        id_modelo: 10,
        marca_vehiculo_id: 8,
        tipo_vehiculo_id: 1,
        nombre: 'Crypton',
        activa: true,
    },
];
