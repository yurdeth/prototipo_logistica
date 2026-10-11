// resources/js/config/rutas.ts
// Tipos y constantes del módulo de rutas, alineados con las tablas:
// rutas.estados_despachos, rutas.despachos, rutas.despacho_paradas y rutas.conexiones
import { colors } from '@/UI/Colors';

/* ---------- Tipos ---------- */
export type EstadoDespacho = 'PLANIFICADO' | 'EN_CURSO' | 'COMPLETADO';

// Estado de una parada según su hora real vs. la estimada
export type EstadoParada =
    | 'a_tiempo'
    | 'con_retraso'
    | 'pendiente'
    | 'atrasada';

export interface Parada {
    id_parada: number;
    orden: number;
    codigo_guia: string; // de envios.envios
    destino: string;
    hora_estimada: string; // ISO
    hora_real: string | null; // ISO (la BD exige hora_real >= hora_estimada)
}

export interface Despacho {
    id_despacho: number;
    codigo: string;
    fecha: string; // YYYY-MM-DD
    sucursal_origen: string;
    conductor: string;
    vehiculo: string;
    estado: EstadoDespacho;
    km_planificados: number | null;
    km_recorridos: number | null;
    costo_estimado: number | null;
    paradas: Parada[];
}

export interface Conexion {
    id_conexion: number;
    origen: string;
    destino: string;
    distancia_km: number;
    tiempo_min: number;
    activa: boolean;
}

/* ---------- Reglas de negocio (ajustables) ---------- */
export const TOLERANCIA_MIN = 15; // minutos de gracia sobre la hora estimada
export const UMBRAL_DESVIACION = 10; // % de km por encima de lo planificado que se considera alerta
export const VELOCIDAD_LENTA = 35; // km/h: conexiones por debajo se marcan como lentas

/* ---------- Presentación ---------- */
export const estadoDespachoInfo: Record<
    EstadoDespacho,
    { label: string; color: string }
> = {
    PLANIFICADO: { label: 'Planificado', color: colors.cian },
    EN_CURSO: { label: 'En curso', color: colors.azulClaro },
    COMPLETADO: { label: 'Completado', color: colors.verde },
};

export const estadoParadaInfo: Record<
    EstadoParada,
    { label: string; color: string }
> = {
    a_tiempo: { label: 'A tiempo', color: colors.verde },
    con_retraso: { label: 'Con retraso', color: colors.amarillo },
    pendiente: { label: 'Pendiente', color: colors.grisMedio },
    atrasada: { label: 'Atrasada', color: colors.rojo },
};

/* ---------- Datos de demostración ---------- */
// Generados relativos a "ahora" para que el seguimiento en curso tenga sentido.
const mulberry32 = (seed: number) => () => {
    seed |= 0;
    seed = (seed + 0x6d2b79f5) | 0;
    let t = Math.imul(seed ^ (seed >>> 15), 1 | seed);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
};

const pad = (n: number) => String(n).padStart(2, '0');
const fechaLocal = (ms: number) => {
    const d = new Date(ms);
    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
};

export const sucursalesDemo = [
    'Sucursal Central',
    'Sucursal Oriente',
    'Sucursal Occidente',
];

// factor = qué tan puntual/eficiente es (menor es mejor), costoKm = costo por km del vehículo
const conductoresDemo = [
    { nombre: 'Luis Martínez', factor: 0.5 },
    { nombre: 'Ana Rivas', factor: 0.7 },
    { nombre: 'Carlos Pérez', factor: 1.7 },
    { nombre: 'Marta Gómez', factor: 0.6 },
    { nombre: 'José Hernández', factor: 1.1 },
    { nombre: 'Sofía Castillo', factor: 0.9 },
    { nombre: 'Roberto Mejía', factor: 1.4 },
];

const vehiculosDemo = [
    { placa: 'P-123-456', costoKm: 0.48 },
    { placa: 'P-234-567', costoKm: 0.52 },
    { placa: 'P-345-678', costoKm: 0.61 },
    { placa: 'P-456-789', costoKm: 0.9 },
    { placa: 'P-567-890', costoKm: 0.55 },
    { placa: 'P-678-901', costoKm: 0.74 },
];

const destinosDemo = [
    'San Salvador',
    'Santa Tecla',
    'Soyapango',
    'San Miguel',
    'Santa Ana',
    'Usulután',
    'Sonsonate',
    'La Unión',
];

export function generarDespachosDemo(): Despacho[] {
    const rnd = mulberry32(77);
    const H = 3600 * 1000;
    const MIN = 60 * 1000;
    const now = Date.now();
    const base = new Date();
    base.setHours(0, 0, 0, 0);
    const pick = <T>(l: T[]): T => l[Math.floor(rnd() * l.length)];

    // [desfase en días, estado]
    const plan: [number, EstadoDespacho][] = [];
    for (let d = -13; d <= -1; d++)
        for (let k = 0; k < 3; k++) plan.push([d, 'COMPLETADO']);
    plan.push(
        [0, 'COMPLETADO'],
        [0, 'EN_CURSO'],
        [0, 'EN_CURSO'],
        [0, 'PLANIFICADO'],
    );
    plan.push([1, 'PLANIFICADO'], [1, 'PLANIFICADO'], [1, 'PLANIFICADO']);

    let guia = 10400;
    let idParada = 1;

    return plan.map(([dia, estado], i) => {
        const cond = pick(conductoresDemo);
        const veh = pick(vehiculosDemo);
        const nParadas = 3 + Math.floor(rnd() * 5);
        const kmPlan = Math.round((35 + rnd() * 150) * 100) / 100;

        // Hora de salida
        let inicio: number;
        if (dia < 0 || dia === 1)
            inicio = base.getTime() + dia * 24 * H + (7.5 + rnd() * 2) * H;
        else if (estado === 'COMPLETADO') inicio = now - 11 * H;
        else if (estado === 'EN_CURSO') inicio = now - (2.5 + rnd() * 1.5) * H;
        else inicio = now + (1 + rnd() * 2) * H;

        const paradas: Parada[] = [];
        let est = inicio;
        for (let n = 1; n <= nParadas; n++) {
            est += (35 + rnd() * 20) * MIN;
            const retraso = Math.round(rnd() * rnd() * 70 * cond.factor) * MIN; // casi siempre pequeño
            let real: number | null = null;
            if (estado === 'COMPLETADO') real = est + retraso;
            else if (
                estado === 'EN_CURSO' &&
                est + retraso < now - 2 * MIN &&
                n < nParadas
            )
                real = est + retraso;
            paradas.push({
                id_parada: idParada++,
                orden: n,
                codigo_guia: `PK-${guia++}`,
                destino: pick(destinosDemo),
                hora_estimada: new Date(est).toISOString(),
                hora_real: real ? new Date(real).toISOString() : null,
            });
        }

        const hechas = paradas.filter((p) => p.hora_real).length;
        const kmReal =
            estado === 'COMPLETADO'
                ? Math.round(
                      kmPlan * (0.94 + rnd() * 0.22 * cond.factor) * 100,
                  ) / 100
                : estado === 'EN_CURSO'
                  ? Math.round(
                        kmPlan *
                            (hechas / nParadas) *
                            (0.95 + rnd() * 0.2) *
                            100,
                    ) / 100
                  : null;

        return {
            id_despacho: i + 1,
            codigo: `DSP-${String(i + 1).padStart(4, '0')}`,
            fecha: fechaLocal(inicio),
            sucursal_origen: pick(sucursalesDemo),
            conductor: cond.nombre,
            vehiculo: veh.placa,
            estado,
            km_planificados: kmPlan,
            km_recorridos: kmReal,
            costo_estimado: Math.round(kmPlan * veh.costoKm * 100) / 100,
            paradas,
        };
    });
}

export function generarConexionesDemo(): Conexion[] {
    const [c, o, w] = sucursalesDemo;
    const filas: [string, string, number, number, boolean][] = [
        [c, o, 138, 120, true],
        [o, c, 138, 128, true],
        [c, w, 64, 125, true], // lenta: tráfico urbano
        [w, c, 64, 118, true],
        [o, w, 182, 190, true],
        [w, o, 182, 175, false],
        [c, 'Sucursal Norte', 22, 48, true],
    ];
    return filas.map(([origen, destino, km, min, activa], i) => ({
        id_conexion: i + 1,
        origen,
        destino,
        distancia_km: km,
        tiempo_min: min,
        activa,
    }));
}
