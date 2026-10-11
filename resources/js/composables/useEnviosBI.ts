// resources/js/composables/useEnviosBI.ts
// Toda la lógica de BI del módulo de envíos: filtros cruzados, KPIs, SLA y agregaciones.
// Los gráficos de la vista salen de aquí, no de datos fijos.
import { computed, reactive, ref, watch, type Ref } from 'vue';
import {
    estadosOrden,
    type Envio,
    type EstadoEnvio,
    type Prioridad,
    type Sla,
} from '@/config/Envios/envios';

const H = 3600 * 1000;
const CERRADOS: EstadoEnvio[] = ['ENTREGADO', 'DEVUELTO', 'FALLIDO'];

export interface Filtros {
    busqueda: string;
    estado: EstadoEnvio | null;
    prioridad: Prioridad | null;
    destino: string | null;
    periodo: 0 | 7 | 30 | 90; // días hacia atrás; 0 = todo
    soloRiesgo: boolean; // solo envíos en riesgo o vencidos
}

export type SortKey = 'fecha_registro' | 'fecha_comprometida' | 'peso_kg';

/* ---------- SLA: ¿se cumple el compromiso de entrega? ---------- */
export function getSla(e: Envio, now = Date.now()): Sla {
    const comprometida = new Date(e.fecha_comprometida).getTime();

    if (e.estado === 'ENTREGADO') {
        const entrega = e.fecha_entrega
            ? new Date(e.fecha_entrega).getTime()
            : now;
        return entrega <= comprometida ? 'a_tiempo' : 'tarde';
    }
    if (e.estado === 'DEVUELTO' || e.estado === 'FALLIDO') return 'incumplido';

    // Envío abierto
    if (now > comprometida) return 'vencido';
    if (comprometida - now <= 24 * H) return 'en_riesgo';
    return 'en_plazo';
}

/* ---------- Formato ---------- */
export const fmtFecha = (iso: string | null): string =>
    iso
        ? new Date(iso).toLocaleString('es-SV', {
              day: '2-digit',
              month: 'short',
              hour: '2-digit',
              minute: '2-digit',
          })
        : '—';

export const fmtDuracion = (horas: number | null): string => {
    if (horas === null) return '—';
    return horas >= 48
        ? `${(horas / 24).toFixed(1)} d`
        : `${horas.toFixed(0)} h`;
};

export function useEnviosBI(envios: Ref<Envio[]>) {
    const now = Date.now();

    const filtros = reactive<Filtros>({
        busqueda: '',
        estado: null,
        prioridad: null,
        destino: null,
        periodo: 30,
        soloRiesgo: false,
    });

    const sort = reactive<{ key: SortKey; dir: 'asc' | 'desc' }>({
        key: 'fecha_registro',
        dir: 'desc',
    });
    const page = ref(1);
    const pageSize = 8;

    /* ---------- Filtro cruzado ----------
       `omit` permite que cada gráfico ignore su propio filtro y así
       siga mostrando las demás opciones (práctica estándar en BI). */
    const aplicar = (omit?: 'estado' | 'prioridad' | 'destino'): Envio[] => {
        const q = filtros.busqueda.trim().toLowerCase();
        const desde = filtros.periodo ? now - filtros.periodo * 24 * H : 0;

        return envios.value.filter((e) => {
            if (desde && new Date(e.fecha_registro).getTime() < desde)
                return false;
            if (
                omit !== 'estado' &&
                filtros.estado &&
                e.estado !== filtros.estado
            )
                return false;
            if (
                omit !== 'prioridad' &&
                filtros.prioridad &&
                e.prioridad !== filtros.prioridad
            )
                return false;
            if (
                omit !== 'destino' &&
                filtros.destino &&
                e.territorio_destino !== filtros.destino
            )
                return false;
            if (filtros.soloRiesgo) {
                const s = getSla(e, now);
                if (s !== 'en_riesgo' && s !== 'vencido') return false;
            }
            if (q) {
                const texto =
                    `${e.codigo_guia} ${e.remitente} ${e.destinatario} ${e.territorio_destino} ${e.direccion_destino}`.toLowerCase();
                if (!texto.includes(q)) return false;
            }
            return true;
        });
    };

    const filtrados = computed(() => aplicar());

    /* ---------- KPIs ---------- */
    const kpis = computed(() => {
        const lista = filtrados.value;
        const slas = lista.map((e) => getSla(e, now));
        const cerrados = lista.filter((e) =>
            CERRADOS.includes(e.estado),
        ).length;
        const aTiempo = slas.filter((s) => s === 'a_tiempo').length;
        const entregados = lista.filter(
            (e) => e.estado === 'ENTREGADO' && e.fecha_entrega,
        );
        const duraciones = entregados.map(
            (e) =>
                (new Date(e.fecha_entrega as string).getTime() -
                    new Date(e.fecha_registro).getTime()) /
                H,
        );
        const fallidos = lista.filter(
            (e) => e.estado === 'FALLIDO' || e.estado === 'DEVUELTO',
        ).length;

        return {
            total: lista.length,
            pesoTotal: lista.reduce((acc, e) => acc + e.peso_kg, 0),
            cumplimiento: cerrados ? (aTiempo / cerrados) * 100 : null,
            aTiempo,
            cerrados,
            enRiesgo: slas.filter((s) => s === 'en_riesgo').length,
            vencidos: slas.filter((s) => s === 'vencido').length,
            tiempoPromedioH: duraciones.length
                ? duraciones.reduce((a, b) => a + b, 0) / duraciones.length
                : null,
            tasaFallo: lista.length ? (fallidos / lista.length) * 100 : 0,
        };
    });

    /* ---------- Embudo por estado ---------- */
    const embudo = computed(() => {
        const lista = aplicar('estado');
        const conteo = new Map<EstadoEnvio, number>();
        lista.forEach((e) =>
            conteo.set(e.estado, (conteo.get(e.estado) ?? 0) + 1),
        );
        const max = Math.max(1, ...estadosOrden.map((s) => conteo.get(s) ?? 0));
        return estadosOrden.map((estado) => ({
            estado,
            total: conteo.get(estado) ?? 0,
            ancho: ((conteo.get(estado) ?? 0) / max) * 100,
        }));
    });

    /* ---------- Tendencia diaria de registros (últimos 14 días) ---------- */
    const tendencia = computed(() => {
        const lista = filtrados.value;
        const dias = 14;
        const hoy = new Date();
        hoy.setHours(0, 0, 0, 0);

        const buckets = Array.from({ length: dias }, (_, i) => {
            const d = new Date(hoy);
            d.setDate(hoy.getDate() - (dias - 1 - i));
            return {
                fecha: d,
                label: `${d.getDate()}/${d.getMonth() + 1}`,
                total: 0,
                incidencias: 0,
            };
        });

        lista.forEach((e) => {
            const d = new Date(e.fecha_registro);
            d.setHours(0, 0, 0, 0);
            const bucket = buckets.find(
                (b) => b.fecha.getTime() === d.getTime(),
            );
            if (!bucket) return;
            bucket.total += 1;
            const s = getSla(e, now);
            if (s === 'vencido' || s === 'incumplido' || s === 'tarde')
                bucket.incidencias += 1;
        });

        const max = Math.max(1, ...buckets.map((b) => b.total));
        return { buckets, max };
    });

    /* ---------- Por prioridad ---------- */
    const porPrioridad = computed(() => {
        const lista = aplicar('prioridad');
        const total = lista.length || 1;
        return (['URGENTE', 'NORMAL', 'BAJA'] as Prioridad[]).map((p) => {
            const items = lista.filter((e) => e.prioridad === p);
            const criticos = items.filter((e) => {
                const s = getSla(e, now);
                return s === 'en_riesgo' || s === 'vencido';
            }).length;
            return {
                prioridad: p,
                total: items.length,
                porcentaje: (items.length / total) * 100,
                criticos,
            };
        });
    });

    /* ---------- Top destinos ---------- */
    const topDestinos = computed(() => {
        const lista = aplicar('destino');
        const mapa = new Map<string, { total: number; incidencias: number }>();
        lista.forEach((e) => {
            const item = mapa.get(e.territorio_destino) ?? {
                total: 0,
                incidencias: 0,
            };
            item.total += 1;
            const s = getSla(e, now);
            if (s === 'vencido' || s === 'incumplido' || s === 'tarde')
                item.incidencias += 1;
            mapa.set(e.territorio_destino, item);
        });
        const filas = [...mapa.entries()]
            .map(([destino, v]) => ({
                destino,
                total: v.total,
                incidencias: v.incidencias,
                pctIncidencias: v.total ? (v.incidencias / v.total) * 100 : 0,
            }))
            .sort((a, b) => b.total - a.total)
            .slice(0, 6);
        const max = Math.max(1, ...filas.map((f) => f.total));
        return filas.map((f) => ({ ...f, ancho: (f.total / max) * 100 }));
    });

    /* ---------- Tabla: orden + paginación ---------- */
    const ordenados = computed(() => {
        const dir = sort.dir === 'asc' ? 1 : -1;
        return [...filtrados.value].sort((a, b) => {
            if (sort.key === 'peso_kg') return (a.peso_kg - b.peso_kg) * dir;
            return (
                (new Date(a[sort.key]).getTime() -
                    new Date(b[sort.key]).getTime()) *
                dir
            );
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

    // Al cambiar cualquier filtro se vuelve a la primera página
    watch(filtros, () => (page.value = 1), { deep: true });

    /* ---------- Acciones ---------- */
    const toggleEstado = (estado: EstadoEnvio) =>
        (filtros.estado = filtros.estado === estado ? null : estado);
    const togglePrioridad = (p: Prioridad) =>
        (filtros.prioridad = filtros.prioridad === p ? null : p);
    const toggleDestino = (d: string) =>
        (filtros.destino = filtros.destino === d ? null : d);

    const hayFiltros = computed(
        () =>
            !!(
                filtros.estado ||
                filtros.prioridad ||
                filtros.destino ||
                filtros.soloRiesgo ||
                filtros.busqueda
            ),
    );

    const limpiar = () => {
        filtros.busqueda = '';
        filtros.estado = null;
        filtros.prioridad = null;
        filtros.destino = null;
        filtros.soloRiesgo = false;
    };

    // Exporta a CSV lo que se está viendo (con filtros aplicados)
    const exportarCsv = () => {
        const cabecera = [
            'Guía',
            'Remitente',
            'Destinatario',
            'Destino',
            'Estado',
            'Prioridad',
            'Peso (kg)',
            'Registro',
            'Compromiso',
            'Entrega',
            'SLA',
        ];
        const filas = ordenados.value.map((e) => [
            e.codigo_guia,
            e.remitente,
            e.destinatario,
            e.territorio_destino,
            e.estado,
            e.prioridad,
            e.peso_kg,
            e.fecha_registro,
            e.fecha_comprometida,
            e.fecha_entrega ?? '',
            getSla(e, now),
        ]);
        const csv = [cabecera, ...filas]
            .map((fila) =>
                fila.map((v) => `"${String(v).replace(/"/g, '""')}"`).join(','),
            )
            .join('\n');
        const url = URL.createObjectURL(
            new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' }),
        );
        const a = document.createElement('a');
        a.href = url;
        a.download = `envios_${new Date().toISOString().slice(0, 10)}.csv`;
        a.click();
        URL.revokeObjectURL(url);
    };

    return {
        now,
        filtros,
        sort,
        page,
        pageSize,
        filtrados,
        kpis,
        embudo,
        tendencia,
        porPrioridad,
        topDestinos,
        pagina,
        totalPaginas,
        ordenarPor,
        toggleEstado,
        togglePrioridad,
        toggleDestino,
        hayFiltros,
        limpiar,
        exportarCsv,
    };
}
