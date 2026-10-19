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
