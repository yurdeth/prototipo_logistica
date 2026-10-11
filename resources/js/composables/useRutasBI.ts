// resources/js/composables/useRutasBI.ts
// Lógica de BI del módulo de rutas: filtros cruzados, puntualidad de paradas,
// desviación de kilómetros, costos por vehículo y velocidad de las conexiones.
import { computed, reactive, ref, watch, type Ref } from 'vue';
import {
    TOLERANCIA_MIN,
    UMBRAL_DESVIACION,
    VELOCIDAD_LENTA,
    type Conexion,
    type Despacho,
    type EstadoDespacho,
    type EstadoParada,
    type Parada,
} from '@/config/Rutas/rutas';

const MIN = 60 * 1000;
const DIA = 24 * 3600 * 1000;

export interface Filtros {
    busqueda: string;
    estado: EstadoDespacho | null;
    conductor: string | null;
    vehiculo: string | null;
    periodo: 1 | 7 | 14 | 0; // 1 = hoy y próximos; 0 = todo
    soloAlertas: boolean;
}

export type SortKey = 'fecha' | 'costo_estimado';

/* ---------- Formato ---------- */
export const fmtHora = (iso: string | null): string =>
    iso
        ? new Date(iso).toLocaleTimeString('es-SV', {
              hour: '2-digit',
              minute: '2-digit',
          })
        : '—';

export const fmtFecha = (yyyyMmDd: string): string => {
    const [y, m, d] = yyyyMmDd.split('-').map(Number);
    return new Date(y, m - 1, d).toLocaleDateString('es-SV', {
        weekday: 'short',
        day: '2-digit',
        month: 'short',
    });
};

export const fmtUsd = (n: number): string =>
    `$${n.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

/* ---------- Reglas por parada y por despacho ---------- */
// La BD exige hora_real >= hora_estimada, así que el retraso nunca es negativo.
export const retrasoMin = (p: Parada): number | null =>
    p.hora_real
        ? Math.round(
              (new Date(p.hora_real).getTime() -
                  new Date(p.hora_estimada).getTime()) /
                  MIN,
          )
        : null;

export function estadoParada(
    p: Parada,
    despacho: Despacho,
    now = Date.now(),
): EstadoParada {
    const r = retrasoMin(p);
    if (r !== null) return r <= TOLERANCIA_MIN ? 'a_tiempo' : 'con_retraso';
    if (
        despacho.estado === 'EN_CURSO' &&
        now > new Date(p.hora_estimada).getTime() + TOLERANCIA_MIN * MIN
    )
        return 'atrasada';
    return 'pendiente';
}

export function resumenParadas(d: Despacho, now = Date.now()) {
    const estados = d.paradas.map((p) => estadoParada(p, d, now));
    const aTiempo = estados.filter((e) => e === 'a_tiempo').length;
    const conRetraso = estados.filter((e) => e === 'con_retraso').length;
    const atrasadas = estados.filter((e) => e === 'atrasada').length;
    const completadas = aTiempo + conRetraso;
    const evaluadas = completadas + atrasadas; // paradas sobre las que ya se puede medir puntualidad
    return {
        total: d.paradas.length,
        completadas,
        aTiempo,
        conRetraso,
        atrasadas,
        evaluadas,
    };
}

// Solo es comparable cuando el despacho terminó (en curso solo lleva un tramo)
export const desviacionKm = (d: Despacho): number | null =>
    d.estado === 'COMPLETADO' && d.km_planificados && d.km_recorridos !== null
        ? ((d.km_recorridos - d.km_planificados) / d.km_planificados) * 100
        : null;

export function tieneAlerta(d: Despacho, now = Date.now()): boolean {
    const r = resumenParadas(d, now);
    const desv = desviacionKm(d);
    return (
        r.atrasadas > 0 ||
        r.conRetraso >= 2 ||
        (desv !== null && desv > UMBRAL_DESVIACION)
    );
}

export const costoPorKm = (d: Despacho): number | null =>
    d.costo_estimado !== null && d.km_planificados
        ? d.costo_estimado / d.km_planificados
        : null;

/* ---------- Conexiones: velocidad media y detección de tramos lentos ---------- */
export function analizarConexiones(lista: Conexion[]) {
    return lista
        .map((c) => {
            const velocidad = c.distancia_km / (c.tiempo_min / 60);
            return { ...c, velocidad, lenta: velocidad < VELOCIDAD_LENTA };
        })
        .sort((a, b) => a.velocidad - b.velocidad);
}

export function useRutasBI(despachos: Ref<Despacho[]>) {
    const now = Date.now();

    const filtros = reactive<Filtros>({
        busqueda: '',
        estado: null,
        conductor: null,
        vehiculo: null,
        periodo: 14,
        soloAlertas: false,
    });

    const sort = reactive<{ key: SortKey; dir: 'asc' | 'desc' }>({
        key: 'fecha',
        dir: 'desc',
    });
    const page = ref(1);
    const pageSize = 8;

    /* ---------- Filtro cruzado (cada gráfico ignora su propio filtro) ---------- */
    const aplicar = (
        omit?: 'estado' | 'conductor' | 'vehiculo',
    ): Despacho[] => {
        const q = filtros.busqueda.trim().toLowerCase();
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);
        const desde = filtros.periodo
            ? hoy.getTime() - (filtros.periodo - 1) * DIA
            : 0;

        return despachos.value.filter((d) => {
            if (desde) {
                const [y, m, dd] = d.fecha.split('-').map(Number);
                if (new Date(y, m - 1, dd).getTime() < desde) return false;
            }
            if (
                omit !== 'estado' &&
                filtros.estado &&
                d.estado !== filtros.estado
            )
                return false;
            if (
                omit !== 'conductor' &&
                filtros.conductor &&
                d.conductor !== filtros.conductor
            )
                return false;
            if (
                omit !== 'vehiculo' &&
                filtros.vehiculo &&
                d.vehiculo !== filtros.vehiculo
            )
                return false;
            if (filtros.soloAlertas && !tieneAlerta(d, now)) return false;
            if (
                q &&
                !`${d.codigo} ${d.conductor} ${d.vehiculo} ${d.sucursal_origen}`
                    .toLowerCase()
                    .includes(q)
            )
                return false;
            return true;
        });
    };

    const filtrados = computed(() => aplicar());

    /* ---------- KPIs ---------- */
    const kpis = computed(() => {
        const lista = filtrados.value;
        const res = lista.map((d) => resumenParadas(d, now));
        const evaluadas = res.reduce((a, r) => a + r.evaluadas, 0);
        const aTiempo = res.reduce((a, r) => a + r.aTiempo, 0);
        const atrasadas = res.reduce((a, r) => a + r.atrasadas, 0);

        const completados = lista.filter((d) => desviacionKm(d) !== null);
        const kmPlanCompletados = completados.reduce(
            (a, d) => a + (d.km_planificados ?? 0),
            0,
        );
        const kmRealCompletados = completados.reduce(
            (a, d) => a + (d.km_recorridos ?? 0),
            0,
        );

        const conCosto = lista.filter(
            (d) => d.costo_estimado !== null && d.km_planificados,
        );
        const costoTotal = conCosto.reduce(
            (a, d) => a + (d.costo_estimado ?? 0),
            0,
        );
        const kmConCosto = conCosto.reduce(
            (a, d) => a + (d.km_planificados ?? 0),
            0,
        );

        return {
            total: lista.length,
            planificados: lista.filter((d) => d.estado === 'PLANIFICADO')
                .length,
            enCurso: lista.filter((d) => d.estado === 'EN_CURSO').length,
            completadosN: lista.filter((d) => d.estado === 'COMPLETADO').length,
            puntualidad: evaluadas ? (aTiempo / evaluadas) * 100 : null,
            aTiempo,
            evaluadas,
            atrasadas,
            desviacion: kmPlanCompletados
                ? ((kmRealCompletados - kmPlanCompletados) /
                      kmPlanCompletados) *
                  100
                : null,
            kmExtra: kmRealCompletados - kmPlanCompletados,
            costoTotal,
            costoPorKm: kmConCosto ? costoTotal / kmConCosto : null,
            conAlerta: lista.filter((d) => tieneAlerta(d, now)).length,
        };
    });

    /* ---------- Estado de despachos ---------- */
    const porEstado = computed(() => {
        const lista = aplicar('estado');
        const total = lista.length || 1;
        return (
            ['EN_CURSO', 'PLANIFICADO', 'COMPLETADO'] as EstadoDespacho[]
        ).map((estado) => {
            const n = lista.filter((d) => d.estado === estado).length;
            return { estado, total: n, porcentaje: (n / total) * 100 };
        });
    });

    /* ---------- Kilómetros por día (últimos 14 días) ---------- */
    const kmPorDia = computed(() => {
        const lista = filtrados.value;
        const dias = 14;
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);
        const buckets = Array.from({ length: dias }, (_, i) => {
            const d = new Date(hoy.getTime() - (dias - 1 - i) * DIA);
            const clave = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
            return {
                clave,
                label: `${d.getDate()}/${d.getMonth() + 1}`,
                plan: 0,
                real: 0,
            };
        });
        lista.forEach((d) => {
            const b = buckets.find((x) => x.clave === d.fecha);
            if (!b) return;
            b.plan += d.km_planificados ?? 0;
            b.real += d.km_recorridos ?? 0;
        });
        const max = Math.max(1, ...buckets.flatMap((b) => [b.plan, b.real]));
        return { buckets, max };
    });

    /* ---------- Puntualidad por conductor (de menor a mayor) ---------- */
    const porConductor = computed(() => {
        const mapa = new Map<
            string,
            { despachos: number; aTiempo: number; evaluadas: number }
        >();
        aplicar('conductor').forEach((d) => {
            const r = resumenParadas(d, now);
            const item = mapa.get(d.conductor) ?? {
                despachos: 0,
                aTiempo: 0,
                evaluadas: 0,
            };
            item.despachos += 1;
            item.aTiempo += r.aTiempo;
            item.evaluadas += r.evaluadas;
            mapa.set(d.conductor, item);
        });
        return [...mapa.entries()]
            .filter(([, v]) => v.evaluadas > 0)
            .map(([conductor, v]) => ({
                conductor,
                ...v,
                pct: (v.aTiempo / v.evaluadas) * 100,
            }))
            .sort((a, b) => a.pct - b.pct)
            .slice(0, 6);
    });

    /* ---------- Rendimiento por vehículo (costo por km) ---------- */
    const porVehiculo = computed(() => {
        const mapa = new Map<
            string,
            {
                despachos: number;
                km: number;
                costo: number;
                kmPlanC: number;
                kmRealC: number;
            }
        >();
        aplicar('vehiculo').forEach((d) => {
            const item = mapa.get(d.vehiculo) ?? {
                despachos: 0,
                km: 0,
                costo: 0,
                kmPlanC: 0,
                kmRealC: 0,
            };
            item.despachos += 1;
            item.km += d.km_planificados ?? 0;
            item.costo += d.costo_estimado ?? 0;
            if (desviacionKm(d) !== null) {
                item.kmPlanC += d.km_planificados ?? 0;
                item.kmRealC += d.km_recorridos ?? 0;
            }
            mapa.set(d.vehiculo, item);
        });
        const filas = [...mapa.entries()]
            .map(([vehiculo, v]) => ({
                vehiculo,
                despachos: v.despachos,
                km: v.km,
                costoPorKm: v.km ? v.costo / v.km : 0,
                desviacion: v.kmPlanC
                    ? ((v.kmRealC - v.kmPlanC) / v.kmPlanC) * 100
                    : null,
            }))
            .sort((a, b) => b.costoPorKm - a.costoPorKm)
            .slice(0, 6);
        const max = Math.max(0.0001, ...filas.map((f) => f.costoPorKm));
        return filas.map((f) => ({ ...f, ancho: (f.costoPorKm / max) * 100 }));
    });

    /* ---------- Tabla ---------- */
    const ordenados = computed(() => {
        const dir = sort.dir === 'asc' ? 1 : -1;
        return [...filtrados.value].sort((a, b) => {
            if (sort.key === 'costo_estimado')
                return (
                    ((a.costo_estimado ?? 0) - (b.costo_estimado ?? 0)) * dir
                );
            return a.fecha === b.fecha
                ? (a.id_despacho - b.id_despacho) * dir
                : a.fecha.localeCompare(b.fecha) * dir;
        });
    });
    const totalPaginas = computed(() =>
        Math.max(1, Math.ceil(ordenados.value.length / pageSize)),
    );
    const pagina = computed(() =>
        ordenados.value.slice(
            (page.value - 1) * pageSize,
            page.value * pageSize,
        ),
    );

    const ordenarPor = (key: SortKey) => {
        if (sort.key === key) sort.dir = sort.dir === 'asc' ? 'desc' : 'asc';
        else {
            sort.key = key;
            sort.dir = 'desc';
        }
    };

    watch(filtros, () => (page.value = 1), { deep: true });

    /* ---------- Acciones ---------- */
    const toggleEstado = (e: EstadoDespacho) =>
        (filtros.estado = filtros.estado === e ? null : e);
    const toggleConductor = (c: string) =>
        (filtros.conductor = filtros.conductor === c ? null : c);
    const toggleVehiculo = (v: string) =>
        (filtros.vehiculo = filtros.vehiculo === v ? null : v);

    const hayFiltros = computed(
        () =>
            !!(
                filtros.estado ||
                filtros.conductor ||
                filtros.vehiculo ||
                filtros.soloAlertas ||
                filtros.busqueda
            ),
    );
    const limpiar = () => {
        filtros.busqueda = '';
        filtros.estado = null;
        filtros.conductor = null;
        filtros.vehiculo = null;
        filtros.soloAlertas = false;
    };

    const exportarCsv = () => {
        const cab = [
            'Despacho',
            'Fecha',
            'Origen',
            'Conductor',
            'Vehículo',
            'Estado',
            'Paradas',
            'Km planificados',
            'Km recorridos',
            'Costo estimado',
        ];
        const filas = ordenados.value.map((d) => [
            d.codigo,
            d.fecha,
            d.sucursal_origen,
            d.conductor,
            d.vehiculo,
            d.estado,
            d.paradas.length,
            d.km_planificados ?? '',
            d.km_recorridos ?? '',
            d.costo_estimado ?? '',
        ]);
        const csv = [cab, ...filas]
            .map((f) =>
                f.map((v) => `"${String(v).replace(/"/g, '""')}"`).join(','),
            )
            .join('\n');
        const url = URL.createObjectURL(
            new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' }),
        );
        const a = document.createElement('a');
        a.href = url;
        a.download = `despachos_${new Date().toISOString().slice(0, 10)}.csv`;
        a.click();
        URL.revokeObjectURL(url);
    };

    return {
        now,
        filtros,
        sort,
        page,
        filtrados,
        kpis,
        porEstado,
        kmPorDia,
        porConductor,
        porVehiculo,
        pagina,
        totalPaginas,
        ordenarPor,
        toggleEstado,
        toggleConductor,
        toggleVehiculo,
        hayFiltros,
        limpiar,
        exportarCsv,
    };
}
