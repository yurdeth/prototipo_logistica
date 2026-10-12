// resources/js/config/Personal/empleados.ts
// Tipos y datos de demostración del módulo de personal.
// Estos datos de demo solo se usan mientras Laravel no envíe los reales.

export interface DepartamentoCatalogo {
    id_departamento: number;
    nombre_departamento: string;
}

export interface PuestoCatalogo {
    id_puesto: number;
    nombre_puesto: string;
    departamento: DepartamentoCatalogo;
}

export interface SucursalEmpleado {
    id_sucursal: number;
    nombre_sucursal: string;
}

export interface Empleado {
    id_empleado: number;
    nombres: string;
    apellidos: string;
    documento_identidad: string;
    telefono: string | null;
    numero_licencia: string | null;
    fecha_contratacion: string | null;
    activo: boolean;
    puesto: PuestoCatalogo;
    sucursal: SucursalEmpleado;
}

// Personal de demostración para el diseño de la vista.
export const empleadosDemo: Empleado[] = [
    {
        id_empleado: 1,
        nombres: 'Juan',
        apellidos: 'Perez',
        documento_identidad: '1234567-8',
        telefono: '70001234',
        numero_licencia: 'LIC-001',
        fecha_contratacion: '2024-03-15',
        activo: true,
        puesto: {
            id_puesto: 1,
            nombre_puesto: 'Conductor',
            departamento: {
                id_departamento: 1,
                nombre_departamento: 'Operaciones',
            },
        },
        sucursal: { id_sucursal: 1, nombre_sucursal: 'Bodega Base SCZ' },
    },
    {
        id_empleado: 2,
        nombres: 'María',
        apellidos: 'Gómez',
        documento_identidad: '8765432-1',
        telefono: null,
        numero_licencia: null,
        fecha_contratacion: '2025-01-10',
        activo: false,
        puesto: {
            id_puesto: 2,
            nombre_puesto: 'Analista de rutas',
            departamento: {
                id_departamento: 1,
                nombre_departamento: 'Operaciones',
            },
        },
        sucursal: { id_sucursal: 1, nombre_sucursal: 'Bodega Base SCZ' },
    },
];

// Opciones de demo para el formulario de registro/edición.
export const puestosDemo: PuestoCatalogo[] = [
    {
        id_puesto: 1,
        nombre_puesto: 'Conductor',
        departamento: {
            id_departamento: 1,
            nombre_departamento: 'Operaciones',
        },
    },
    {
        id_puesto: 2,
        nombre_puesto: 'Analista de rutas',
        departamento: {
            id_departamento: 1,
            nombre_departamento: 'Operaciones',
        },
    },
    {
        id_puesto: 3,
        nombre_puesto: 'Bodeguero',
        departamento: { id_departamento: 2, nombre_departamento: 'Bodega' },
    },
];

export const sucursalesEmpleadoDemo: SucursalEmpleado[] = [
    { id_sucursal: 1, nombre_sucursal: 'Bodega Base SCZ' },
];
