// Tipos y apariencia del mapa de rutas (Leaflet).
import { colors } from '@/UI/Colors';
import { estadoInfo } from '@/config/Envios/envios';

/* ───────── Tipos de lo que envía GET /rutas/mapa/datos ───────── */
export interface Punto {
    lat: number;
    lng: number;
}

export interface ParadaMapa {
    id: number;
    orden: number;
    envio_id: number;
    codigo_guia: string | null;
    destino: string | null;
    direccion: string | null;
    estado_envio: string | null;
    hora_estimada: string | null;
    hora_real: string | null;
    lat: number | null;
    lng: number | null;
    ubicacion_aproximada: boolean;
    incidencias: number;
}

export interface EnvioMapa {
    id: number;
    codigo_guia: string;
    direccion: string;
    fecha_registro: string;
    estado: string;
    territorio_id: number;
    territorio: string;
    tipo_territorio: string;
    territorio_padre: string | null;
    ruta_id: number | null;
    ruta_codigo: string | null;
    ruta_fecha: string | null;
    ruta_estado: string | null;
    ruta_sucursal: string | null;
    orden: number | null;
    hora_estimada: string | null;
    hora_real: string | null;
    lat: number | null;
    lng: number | null;
    incidencias: number;
}

export interface DespachoMapa {
    id: number;
    codigo: string;
    fecha: string | null;
    estado: string | null;
    sucursal: string | null;
    origen: Punto | null;
    km_planificados: number | null;
    km_recorridos: number | null;
    incidencias: number;
    minutos_retraso: number;
    paradas: ParadaMapa[];
}

export interface SucursalMapa {
    id: number;
    codigo: string;
    nombre: string;
    direccion: string | null;
    activa: boolean;
    punto: Punto | null;
}

export interface DatosMapa {
    generado: string;
    filtros: {
        dias: number;
        estado: string | null;
        ruta: number | null;
        territorio: number | null;
    };
    kpis: {
        total: number;
        pendientes: number;
        en_ruta: number;
        entregados: number;
        fallidos: number;
        envios_con_incidencias: number;
        incidencias: number;
        tasa_entrega: number;
    };
    estados: { estado: string; total: number }[];
    territorios: {
        id: number;
        nombre: string;
        tipo: string;
        padre_id: number | null;
        padre: string | null;
        total: number;
        entregados: number;
        pendientes: number;
        en_ruta: number;
        envios_con_incidencias: number;
        tasa_entrega: number;
    }[];
    catalogos: {
        estados: string[];
        rutas: { id: number; codigo: string; fecha: string; estado: string }[];
        territorios: {
            id: number;
            nombre: string;
            tipo: string;
            padre_id: number | null;
            padre: string | null;
        }[];
    };
    envios: EnvioMapa[];
    /** Panel de recorridos paginado en el servidor (scroll infinito). */
    rutas: { data: DespachoMapa[]; hay_mas: boolean };
    sucursales: SucursalMapa[];
}

/* ───────── Estado de cada parada (según el estado de su envío) ───────── */
export type ClaveVisual =
    | 'registrado'
    | 'recolectado'
    | 'en_transito'
    | 'en_centro'
    | 'en_ruta'
    | 'entregado'
    | 'devuelto'
    | 'fallido'
    | 'desconocido';

export interface Visual {
    clave: ClaveVisual;
    etiqueta: string;
    color: string;
}

export const VISUALES: Record<ClaveVisual, Visual> = {
    registrado: {
        clave: 'registrado',
        etiqueta: 'Registrado',
        color: colors.grisMedio,
    },
    recolectado: {
        clave: 'recolectado',
        etiqueta: 'Recolectado',
        color: colors.cian,
    },
    en_transito: {
        clave: 'en_transito',
        etiqueta: 'En tránsito',
        color: colors.azulClaro,
    },
    en_centro: { clave: 'en_centro', etiqueta: 'En centro', color: '#7C3AED' },
    en_ruta: { clave: 'en_ruta', etiqueta: 'En ruta', color: colors.amarillo },
    entregado: {
        clave: 'entregado',
        etiqueta: 'Entregado',
        color: colors.verde,
    },
    devuelto: {
        clave: 'devuelto',
        etiqueta: 'Devuelto',
        color: colors.amarilloClaro,
    },
    fallido: { clave: 'fallido', etiqueta: 'Fallido', color: colors.rojo },
    desconocido: {
        clave: 'desconocido',
        etiqueta: 'Sin estado',
        color: colors.grisMedio,
    },
};

export const ORDEN_VISUALES: ClaveVisual[] = [
    'registrado',
    'recolectado',
    'en_transito',
    'en_centro',
    'en_ruta',
    'entregado',
    'devuelto',
    'fallido',
    'desconocido',
];

const CLAVE_POR_ESTADO_ENVIO: Record<string, ClaveVisual> = {
    REGISTRADO: 'registrado',
    RECOLECTADO: 'recolectado',
    EN_TRANSITO: 'en_transito',
    EN_CENTRO: 'en_centro',
    EN_RUTA: 'en_ruta',
    ENTREGADO: 'entregado',
    DEVUELTO: 'devuelto',
    FALLIDO: 'fallido',
};

/** Cualquier estado nulo o que no se conozca cae en "desconocido" (nunca lanza error). */
export const estadoVisual = (estado?: string | null): Visual => {
    const clave = estado
        ? CLAVE_POR_ESTADO_ENVIO[estado.toUpperCase()]
        : undefined;

    return VISUALES[clave ?? 'desconocido'];
};

/** Nombre legible del estado del envío ("En tránsito"), con respaldo para valores desconocidos. */
export const etiquetaEnvio = (estado?: string | null): string => {
    if (!estado) return 'Sin estado';

    const info = (estadoInfo as Record<string, { label: string } | undefined>)[
        estado
    ];

    return info?.label ?? estado;
};

/* ───────── Estado del despacho (color y trazo de la ruta) ───────── */
export interface VisualRuta {
    etiqueta: string;
    color: string;
    discontinua: boolean;
}

const RUTAS: Record<string, VisualRuta> = {
    PLANIFICADO: {
        etiqueta: 'Planificado',
        color: colors.amarillo,
        discontinua: true,
    },
    EN_CURSO: {
        etiqueta: 'En curso',
        color: colors.azulClaro,
        discontinua: false,
    },
    COMPLETADO: {
        etiqueta: 'Completado',
        color: colors.verde,
        discontinua: false,
    },
};

export const ESTADOS_DESPACHO = [
    'PLANIFICADO',
    'EN_CURSO',
    'COMPLETADO',
] as const;

export const visualRuta = (estado?: string | null): VisualRuta =>
    (estado && RUTAS[estado.toUpperCase()]) || {
        etiqueta: estado ?? 'Sin estado',
        color: colors.grisMedio,
        discontinua: false,
    };

/* ───────── Utilidades ───────── */
/** Los datos vienen de la base: se escapan antes de insertarlos en el HTML de un popup. */
export const escapeHtml = (valor: unknown): string =>
    typeof valor === 'string'
        ? valor
        : valor == null
          ? ''
          : JSON.stringify(valor)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');

export const fmtHora = (iso: string | null): string => {
    if (!iso) return '—';

    const fecha = new Date(iso);

    return Number.isNaN(fecha.getTime())
        ? '—'
        : fecha.toLocaleTimeString('es-SV', {
              hour: '2-digit',
              minute: '2-digit',
          });
};

export const fmtDia = (valor: string | null): string => {
    if (!valor) return '—';

    const fecha = new Date(valor);

    return Number.isNaN(fecha.getTime())
        ? '—'
        : fecha.toLocaleDateString('es-SV', {
              day: '2-digit',
              month: 'short',
              year: 'numeric',
          });
};
