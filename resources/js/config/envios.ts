// resources/js/config/envios.ts
// Tipos y constantes del módulo de envíos, alineados con las tablas:
// envios.clientes, envios.estados_envios, envios.envios y envios.envio_eventos
import { colors } from '@/UI/Colors';

/* ---------- Tipos ---------- */
export type EstadoEnvio =
    | 'REGISTRADO'
    | 'RECOLECTADO'
    | 'EN_TRANSITO'
    | 'EN_CENTRO'
    | 'EN_RUTA'
    | 'ENTREGADO'
    | 'DEVUELTO'
    | 'FALLIDO';

export type Prioridad = 'BAJA' | 'NORMAL' | 'URGENTE';

// Cumplimiento del compromiso de entrega (se calcula con fecha_comprometida)
export type Sla = 'a_tiempo' | 'tarde' | 'en_plazo' | 'en_riesgo' | 'vencido' | 'incumplido';

export interface EnvioEvento {
    id_evento: number;
    estado: EstadoEnvio;
    ocurrido_en: string; // ISO
    nota: string | null;
}

export interface Envio {
    id_envio: number;
    codigo_guia: string;
    remitente: string;
    destinatario: string;
    sucursal_origen: string;
    direccion_destino: string;
    territorio_destino: string;
    peso_kg: number;
    volumen_m3: number | null;
    estado: EstadoEnvio;
    prioridad: Prioridad;
    fecha_registro: string; // ISO
    fecha_comprometida: string; // ISO
    fecha_entrega: string | null; // fecha del evento ENTREGADO
    eventos: EnvioEvento[];
}

/* ---------- Constantes de presentación ---------- */
// Orden lógico del embudo
export const estadosOrden: EstadoEnvio[] = [
    'REGISTRADO',
    'RECOLECTADO',
    'EN_TRANSITO',
    'EN_CENTRO',
    'EN_RUTA',
    'ENTREGADO',
    'DEVUELTO',
    'FALLIDO',
];

export const estadoInfo: Record<EstadoEnvio, { label: string; color: string }> = {
    REGISTRADO: { label: 'Registrado', color: colors.grisMedio },
    RECOLECTADO: { label: 'Recolectado', color: colors.cian },
    EN_TRANSITO: { label: 'En tránsito', color: colors.azulClaro },
    EN_CENTRO: { label: 'En centro', color: colors.azulClaro },
    EN_RUTA: { label: 'En ruta', color: colors.azulClaro },
    ENTREGADO: { label: 'Entregado', color: colors.verde },
    DEVUELTO: { label: 'Devuelto', color: colors.amarillo },
    FALLIDO: { label: 'Fallido', color: colors.rojo },
};

export const prioridadInfo: Record<Prioridad, { label: string; color: string }> = {
    BAJA: { label: 'Baja', color: colors.grisMedio },
    NORMAL: { label: 'Normal', color: colors.azulClaro },
    URGENTE: { label: 'Urgente', color: colors.naranjaClaro },
};

export const slaInfo: Record<Sla, { label: string; color: string }> = {
    a_tiempo: { label: 'A tiempo', color: colors.verde },
    tarde: { label: 'Entregado tarde', color: colors.amarillo },
    en_plazo: { label: 'En plazo', color: colors.cian },
    en_riesgo: { label: 'En riesgo', color: colors.naranjaClaro },
    vencido: { label: 'Vencido', color: colors.rojo },
    incumplido: { label: 'No entregado', color: colors.rojo },
};

/* ---------- Datos de demostración ---------- */
// Se generan relativos a "ahora" para que los KPIs de SLA tengan sentido.
// Se usan solo mientras la página no reciba `envios` desde Laravel.
const mulberry32 = (seed: number) => () => {
    seed |= 0;
    seed = (seed + 0x6d2b79f5) | 0;
    let t = Math.imul(seed ^ (seed >>> 15), 1 | seed);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
};

export function generarEnviosDemo(total = 70): Envio[] {
    const rnd = mulberry32(2026);
    const H = 3600 * 1000;
    const now = Date.now();
    const pick = <T>(list: T[]): T => list[Math.floor(rnd() * list.length)];

    const clientes = [
        'Distribuidora Andina', 'Comercial Sur', 'Importadora Norte', 'Logística Pacífico',
        'Grupo Maya', 'Farmacias Vida', 'Ferretería Central', 'María López', 'Carlos Pérez', 'Ana Rivas',
    ];
    const territorios = [
        'San Salvador', 'Santa Tecla', 'Soyapango', 'San Miguel',
        'Santa Ana', 'Usulután', 'Sonsonate', 'La Unión',
    ];
    const sucursales = ['Sucursal Central', 'Sucursal Oriente', 'Sucursal Occidente'];
    const flujo: EstadoEnvio[] = ['REGISTRADO', 'RECOLECTADO', 'EN_TRANSITO', 'EN_CENTRO', 'EN_RUTA'];

    return Array.from({ length: total }, (_, i) => {
        const registro = now - rnd() * 30 * 24 * H;
        const comprometida = registro + (24 + Math.floor(rnd() * 72)) * H;
        const edad = now - registro;
        const r = rnd();
        const rp = rnd();
        const prioridad: Prioridad = rp < 0.15 ? 'URGENTE' : rp < 0.8 ? 'NORMAL' : 'BAJA';

        let estado: EstadoEnvio;
        if (edad < 6 * H) estado = pick<EstadoEnvio>(['REGISTRADO', 'RECOLECTADO']);
        else if (edad < 36 * H) estado = pick<EstadoEnvio>(['RECOLECTADO', 'EN_TRANSITO', 'EN_CENTRO', 'EN_RUTA']);
        else if (r < 0.78) estado = 'ENTREGADO';
        else if (r < 0.86) estado = 'FALLIDO';
        else if (r < 0.92) estado = 'DEVUELTO';
        else estado = pick<EstadoEnvio>(['EN_TRANSITO', 'EN_CENTRO', 'EN_RUTA']); // atascado → vencido

        // Línea de tiempo (envio_eventos)
        const etapas: EstadoEnvio[] = flujo.includes(estado)
            ? flujo.slice(0, flujo.indexOf(estado) + 1)
            : [...flujo, estado];
        const fin = estado === 'ENTREGADO'
            ? Math.min(registro + (comprometida - registro) * (0.6 + rnd() * 0.7), now - 10 * 60 * 1000)
            : Math.min(now - 5 * 60 * 1000, registro + Math.max(edad, H));
        const eventos: EnvioEvento[] = etapas.map((et, k) => ({
            id_evento: i * 10 + k,
            estado: et,
            ocurrido_en: new Date(registro + ((fin - registro) * k) / Math.max(etapas.length - 1, 1)).toISOString(),
            nota: et === 'FALLIDO' ? 'Destinatario ausente en dos intentos' : et === 'DEVUELTO' ? 'Devuelto a sucursal de origen' : null,
        }));

        const remitente = pick(clientes);
        let destinatario = pick(clientes);
        if (destinatario === remitente) destinatario = clientes[(clientes.indexOf(remitente) + 1) % clientes.length];

        return {
            id_envio: i + 1,
            codigo_guia: `PK-${10400 + i}`,
            remitente,
            destinatario,
            sucursal_origen: pick(sucursales),
            direccion_destino: `Col. ${pick(['Escalón', 'Centro', 'Las Palmas', 'San Benito', 'Miramonte'])}, calle ${1 + Math.floor(rnd() * 40)}`,
            territorio_destino: pick(territorios),
            peso_kg: Math.round((0.5 + rnd() * 40) * 100) / 100,
            volumen_m3: rnd() < 0.7 ? Math.round((0.01 + rnd() * 1.5) * 100) / 100 : null,
            estado,
            prioridad,
            fecha_registro: new Date(registro).toISOString(),
            fecha_comprometida: new Date(comprometida).toISOString(),
            fecha_entrega: estado === 'ENTREGADO' ? new Date(fin).toISOString() : null,
            eventos,
        };
    });
}