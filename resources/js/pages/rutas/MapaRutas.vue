<script setup lang="ts">
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import type * as Leaflet from 'leaflet';
import 'leaflet/dist/leaflet.css';
import 'leaflet-draw/dist/leaflet.draw.css';
import { colors } from '@/UI/Colors';
import {
    ESTADOS_DESPACHO,
    ORDEN_VISUALES,
    VISUALES,
    escapeHtml,
    estadoVisual,
    etiquetaEnvio,
    fmtDia,
    visualRuta,
    type ClaveVisual,
    type DespachoMapa,
    type EnvioMapa,
    type GeometriaPoligono,
    type ParadaMapa,
    type SucursalMapa,
    type Visual,
    type VisualRuta,
    type ZonaMapa,
} from '@/config/Rutas/Mapa';

// Mapa de rutas con Leaflet. Recibe los datos ya filtrados y los dibuja:
// zonas, sucursales, una polilínea por despacho y un marcador por parada.
const props = defineProps<{
    sucursales: SucursalMapa[];
    despachos: DespachoMapa[];
    envios: EnvioMapa[];
    zonas: ZonaMapa[];
    seleccionado: number | null;
    modoZonas: boolean;
    mostrarRutas: boolean;
}>();

const emit = defineEmits<{
    seleccionar: [id: number];
    dibujada: [geometria: GeometriaPoligono];
}>();

const CENTRO_INICIAL: [number, number] = [13.7, -89.2]; // El Salvador
const LIMITE_EL_SALVADOR = {
    south: 13.1,
    west: -90.2,
    north: 14.45,
    east: -87.45,
};
const contenedor = ref<HTMLDivElement | null>(null);
const listo = ref(false);
const leyendaAbierta = ref(false);

// Los objetos de Leaflet se guardan en variables normales: Vue no debe convertirlos en reactivos.
let L: typeof Leaflet;
let mapa: Leaflet.Map | null = null;
let capaZonas: Leaflet.LayerGroup;
let capaSucursales: Leaflet.LayerGroup;
let capaRutas: Leaflet.LayerGroup;
let capaParadas: Leaflet.LayerGroup;
let capaBorrador: Leaflet.FeatureGroup;
let controlDibujo: Leaflet.Control | null = null;
let observador: ResizeObserver | null = null;
let firmaBase = '';
let ajustado = false;
const rutas = new Map<number, Leaflet.Polyline>();

/* ───────── Posiciones ───────── */
type Coord = [number, number];

// Varias paradas en la misma zona caerían exactamente en el mismo punto: se separan un poco
// (siempre igual para la misma parada) para que se puedan ver y pulsar una por una.
const posicion = (lat: number | null, lng: number | null): Coord | null => {
    if (lat === null || lng === null) return null;
    return [lat, lng];
};

const posicionParada = (p: ParadaMapa): Coord | null => posicion(p.lat, p.lng);

const posicionEnvio = (envio: EnvioMapa): Coord | null =>
    posicion(envio.lat, envio.lng);

const puntosRuta = (d: DespachoMapa): Coord[] => {
    const puntos: Coord[] = [];

    if (d.origen) puntos.push([d.origen.lat, d.origen.lng]);
    for (const p of d.paradas) {
        const pos = posicionParada(p);
        if (pos) puntos.push(pos);
    }

    return puntos;
};

/* ───────── Iconos y popups ───────── */
const iconoParada = (v: Visual, orden: number | null): Leaflet.DivIcon =>
    L.divIcon({
        className: 'marcador-parada',
        iconSize: [30, 38],
        iconAnchor: [15, 37],
        popupAnchor: [0, -34],
        html: `<svg width="30" height="38" viewBox="0 0 30 38" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M15 1C7.8 1 2 6.8 2 14c0 9.5 13 23 13 23s13-13.5 13-23C28 6.8 22.2 1 15 1z" fill="${v.color}" stroke="#ffffff" stroke-width="2"/>
            <circle cx="15" cy="14" r="7" fill="#ffffff"/>
            <text x="15" y="17.5" text-anchor="middle" font-size="10" font-weight="700" font-family="system-ui,sans-serif" fill="#1f2937">${orden ?? '•'}</text>
        </svg>`,
    });

const iconoSucursal = (activa: boolean): Leaflet.DivIcon =>
    L.divIcon({
        className: 'marcador-sucursal',
        iconSize: [28, 28],
        iconAnchor: [14, 14],
        popupAnchor: [0, -14],
        html: `<div style="width:28px;height:28px;border-radius:8px;background:${activa ? '#1e293b' : '#94a3b8'};border:2px solid #fff;box-shadow:0 1px 4px rgba(0,0,0,.35);display:flex;align-items:center;justify-content:center">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 9h1M9 13h1M14 9h1M14 13h1"/></svg>
        </div>`,
    });

const fila = (etiqueta: string, valor: unknown): string =>
    `<div style="display:flex;justify-content:space-between;gap:12px;margin-top:3px"><span style="color:#64748b">${etiqueta}</span><span style="text-align:right;font-weight:500">${escapeHtml(valor ?? '—')}</span></div>`;

const popupEnvio = (envio: EnvioMapa): string => {
    const visual = estadoVisual(envio.estado);

    return `<div style="min-width:210px;font:13px/1.4 system-ui,sans-serif;color:#0f172a">
        <div style="font-weight:700;margin-bottom:6px;font-family:ui-monospace,monospace">${escapeHtml(envio.codigo_guia)}</div>
        <span style="display:inline-block;padding:2px 8px;border-radius:6px;font-size:12px;font-weight:600;color:#1f2937;background:${visual.color}40;border:1px solid ${visual.color}">${escapeHtml(etiquetaEnvio(envio.estado))}</span>
        <div style="margin-top:6px">
            ${fila('Territorio', envio.territorio)}
            ${fila('Dirección', envio.direccion)}
            ${fila('Despacho', envio.ruta_codigo ?? 'Sin despacho')}
            ${fila('Incidencias', envio.incidencias)}
            ${fila('Ubicación', 'Última ubicación reportada')}
        </div>
    </div>`;
};

const popupSucursal = (s: SucursalMapa): string =>
    `<div style="min-width:190px;font:13px/1.4 system-ui,sans-serif;color:#0f172a">
        <div style="font-weight:700;margin-bottom:4px">${escapeHtml(s.nombre)}</div>
        ${fila('Código', s.codigo)}
        ${fila('Dirección', s.direccion)}
        ${fila('Estado', s.activa ? 'Activa' : 'Inactiva')}
    </div>`;

const popupRuta = (d: DespachoMapa, v: VisualRuta): string => {
    const ubicadas = d.paradas.filter((p) => posicionParada(p) !== null).length;

    return `<div style="min-width:190px;font:13px/1.4 system-ui,sans-serif;color:#0f172a">
        <div style="font-weight:700;margin-bottom:4px">${escapeHtml(d.codigo)}</div>
        ${fila('Estado', v.etiqueta)}
        ${fila('Fecha', fmtDia(d.fecha))}
        ${fila('Origen', d.sucursal)}
        ${fila('Paradas', `${ubicadas} de ${d.paradas.length} ubicadas`)}
    </div>`;
};

/* ───────── Dibujo de zonas, sucursales y rutas ───────── */
const dibujarBase = () => {
    const firma = JSON.stringify([
        props.zonas,
        props.sucursales,
        props.modoZonas,
    ]);
    if (firma === firmaBase) return;
    firmaBase = firma;

    capaZonas.clearLayers();
    for (const z of props.zonas) {
        try {
            const zona = L.geoJSON(
                {
                    type: 'Feature',
                    properties: {},
                    geometry: z.geometry,
                } as never,
                {
                    style: {
                        color: colors.azulClaro,
                        weight: 1.5,
                        opacity: 0.8,
                        fillColor: colors.azulClaro,
                        fillOpacity: 0.08,
                    },
                    // Mientras se dibuja, las zonas existentes no deben interceptar los clics.
                    interactive: !props.modoZonas,
                },
            );
            zona.bindTooltip(escapeHtml(z.nombre), { sticky: true });
            capaZonas.addLayer(zona);
        } catch {
            // Una geometría dañada no debe romper el resto del mapa.
        }
    }

    capaSucursales.clearLayers();
    for (const s of props.sucursales) {
        if (!s.punto) continue;

        L.marker([s.punto.lat, s.punto.lng], {
            icon: iconoSucursal(s.activa),
            title: s.nombre,
            zIndexOffset: -100,
        })
            .bindPopup(popupSucursal(s), { maxWidth: 280 })
            .addTo(capaSucursales);
    }
};

const estiloRuta = (
    v: VisualRuta,
    seleccionada: boolean,
    hayseleccion: boolean,
) => ({
    color: v.color,
    weight: seleccionada ? 7 : 4,
    opacity: hayseleccion && !seleccionada ? 0.3 : 0.9,
    dashArray: v.discontinua ? '8 8' : undefined,
    lineJoin: 'round' as const,
});

const sincronizarRutas = () => {
    const rutasVistas = new Set<number>();
    const hayseleccion = props.seleccionado !== null;

    for (const d of props.despachos) {
        const v = visualRuta(d.estado);
        const esSeleccion = d.id === props.seleccionado;
        const puntos = puntosRuta(d);

        // Polilínea del despacho: origen y paradas en orden (necesita al menos dos puntos).
        if ((props.mostrarRutas || esSeleccion) && puntos.length >= 2) {
            rutasVistas.add(d.id);
            const estilo = estiloRuta(v, esSeleccion, hayseleccion);
            let linea = rutas.get(d.id);

            if (linea) {
                linea.setLatLngs(puntos);
                linea.setStyle(estilo);
                linea.setPopupContent(popupRuta(d, v));
            } else {
                linea = L.polyline(puntos, estilo)
                    .bindPopup(popupRuta(d, v), { maxWidth: 280 })
                    .addTo(capaRutas);
                linea.on('click', () => emit('seleccionar', d.id));
                rutas.set(d.id, linea);
            }

            linea.bindTooltip(
                `${escapeHtml(d.codigo)} · ${escapeHtml(v.etiqueta)}`,
                { sticky: true },
            );
            if (esSeleccion) linea.bringToFront();
        }
    }

    // Se quitan las rutas que ya no existen (por ejemplo, al cambiar un filtro).
    for (const [id, linea] of rutas) {
        if (!rutasVistas.has(id)) {
            capaRutas.removeLayer(linea);
            rutas.delete(id);
        }
    }
};

const sincronizarEnvios = () => {
    capaParadas.clearLayers();

    const ubicados = props.envios.filter(
        (envio) => envio.lat !== null && envio.lng !== null,
    );

    for (const envio of ubicados) {
        const pos = posicionEnvio(envio);
        if (!pos) continue;

        const visual = estadoVisual(envio.estado);
        const marcador = L.marker(pos, {
            icon: iconoParada(visual, envio.orden),
            title: `${envio.codigo_guia} · ${etiquetaEnvio(envio.estado)}`,
            riseOnHover: true,
            opacity:
                props.seleccionado !== null &&
                envio.ruta_id !== props.seleccionado
                    ? 0.45
                    : 1,
        })
            .bindPopup(popupEnvio(envio), { maxWidth: 300 })
            .addTo(capaParadas);
        const rutaId = envio.ruta_id;
        if (rutaId !== null) {
            marcador.on('click', () => emit('seleccionar', rutaId));
        }
    }
};

/** Centra el mapa en lo que hay: la selección, o todas las rutas y marcadores visibles. */
const ajustarVista = () => {
    if (!mapa) return;

    const sel = props.seleccionado;
    const envios =
        sel !== null
            ? props.envios.filter((envio) => envio.ruta_id === sel)
            : props.envios;
    const puntos: Coord[] = envios.flatMap((envio) => {
        const punto = posicionEnvio(envio);
        return punto ? [punto] : [];
    });

    if (!puntos.length) {
        const lista =
            sel !== null
                ? props.despachos.filter((d) => d.id === sel)
                : props.despachos;
        puntos.push(...lista.flatMap(puntosRuta));
    }

    if (!puntos.length && sel === null) {
        for (const s of props.sucursales) {
            if (s.punto) puntos.push([s.punto.lat, s.punto.lng]);
        }
    }

    if (!puntos.length) {
        mapa.setView(CENTRO_INICIAL, 7);

        return;
    }

    mapa.fitBounds(L.latLngBounds(puntos), { padding: [48, 48], maxZoom: 14 });
};

const sincronizar = () => {
    if (!mapa) return;

    dibujarBase();
    sincronizarRutas();
    sincronizarEnvios();

    if (
        !ajustado &&
        (props.envios.length ||
            props.despachos.length ||
            props.sucursales.length)
    ) {
        ajustado = true;
        ajustarVista();
    }
};

/* ───────── Dibujo de zonas (Leaflet.draw) ───────── */
interface TextosDibujo {
    draw: {
        toolbar: {
            actions: { title: string; text: string };
            finish: { title: string; text: string };
            undo: { title: string; text: string };
            buttons: { polygon: string };
        };
        handlers: {
            polygon: { tooltip: { start: string; cont: string; end: string } };
        };
    };
}

interface EventoDibujoCreado {
    layer: Leaflet.Layer;
}

const traducirDibujo = () => {
    const t = (L as unknown as { drawLocal: TextosDibujo }).drawLocal;

    t.draw.toolbar.actions = { title: 'Cancelar el dibujo', text: 'Cancelar' };
    t.draw.toolbar.finish = { title: 'Terminar el dibujo', text: 'Terminar' };
    t.draw.toolbar.undo = {
        title: 'Quitar el último punto',
        text: 'Quitar último punto',
    };
    t.draw.toolbar.buttons.polygon = 'Dibujar una zona';
    t.draw.handlers.polygon.tooltip = {
        start: 'Haz clic para empezar a dibujar la zona.',
        cont: 'Haz clic para continuar dibujando la zona.',
        end: 'Haz clic en el primer punto para cerrar la zona.',
    };
};

const alCrearZona = (evento: Leaflet.LeafletEvent) => {
    const { layer } = evento as unknown as EventoDibujoCreado;

    capaBorrador.clearLayers();
    capaBorrador.addLayer(layer);

    const geometria = (layer as Leaflet.Polygon).toGeoJSON()
        .geometry as GeometriaPoligono;
    emit('dibujada', geometria);
};

const activarDibujo = () => {
    if (!mapa || controlDibujo) return;

    capaBorrador.clearLayers();
    const ControlDraw = (
        L.Control as unknown as {
            Draw: new (options: object) => Leaflet.Control;
        }
    ).Draw;
    controlDibujo = new ControlDraw({
        position: 'topright',
        draw: {
            polygon: {
                allowIntersection: false,
                showArea: false, // showArea falla en leaflet-draw 1.0.4
                shapeOptions: { color: colors.azulClaro, weight: 3 },
            },
            polyline: false,
            rectangle: false,
            circle: false,
            marker: false,
            circlemarker: false,
        },
        edit: false,
    });
    mapa.addControl(controlDibujo);
};

const desactivarDibujo = () => {
    if (!mapa || !controlDibujo) return;

    mapa.removeControl(controlDibujo);
    controlDibujo = null;
    capaBorrador.clearLayers();
};

const limpiarBorrador = () => capaBorrador?.clearLayers();

/* ───────── Ciclo de vida ───────── */
onMounted(async () => {
    // Leaflet se carga solo al abrir esta vista (no pesa en el resto de la aplicación).
    const modulo = await import('leaflet');
    L = ((modulo as unknown as { default?: typeof Leaflet }).default ??
        modulo) as typeof Leaflet;

    // Leaflet.draw es un plugin clásico que espera encontrar L como variable global.
    (window as unknown as { L: typeof Leaflet }).L = L;
    // @ts-expect-error leaflet-draw no incluye declaraciones de tipos.
    await import('leaflet-draw');

    if (!contenedor.value) return; // la vista se cerró mientras se cargaba

    traducirDibujo();

    mapa = L.map(contenedor.value, {
        center: CENTRO_INICIAL,
        zoom: 7,
        minZoom: 6,
        maxZoom: 13,
        maxBounds: [
            [LIMITE_EL_SALVADOR.south, LIMITE_EL_SALVADOR.west],
            [LIMITE_EL_SALVADOR.north, LIMITE_EL_SALVADOR.east],
        ],
        maxBoundsViscosity: 1.0,
        zoomControl: true,
    });
    L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution:
            '&copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>',
    }).addTo(mapa);

    capaZonas = L.layerGroup().addTo(mapa);
    capaRutas = L.layerGroup().addTo(mapa);
    capaSucursales = L.layerGroup().addTo(mapa);
    capaParadas = L.layerGroup().addTo(mapa);
    capaBorrador = L.featureGroup().addTo(mapa);

    mapa.on('draw:created', alCrearZona);

    // El mapa se redimensiona con su contenedor (por ejemplo, al cambiar el ancho de la pantalla).
    observador = new ResizeObserver(() => mapa?.invalidateSize());
    observador.observe(contenedor.value);

    listo.value = true;
    sincronizar();
    if (props.modoZonas) activarDibujo();
});

onBeforeUnmount(() => {
    observador?.disconnect();
    mapa?.remove();
    mapa = null;
    rutas.clear();
});

watch(
    [
        () => props.despachos,
        () => props.envios,
        () => props.sucursales,
        () => props.zonas,
        () => props.seleccionado,
        () => props.modoZonas,
        () => props.mostrarRutas,
    ],
    () => listo.value && sincronizar(),
);

// Al elegir un despacho, el mapa se acerca a su ruta; al quitar la selección, vuelve a mostrar todo.
watch(
    () => props.seleccionado,
    async () => {
        if (!listo.value) return;
        await nextTick();
        ajustarVista();
    },
);

watch(
    () => props.modoZonas,
    (activo) => {
        if (!listo.value) return;
        if (activo) activarDibujo();
        else desactivarDibujo();
    },
);

defineExpose({ ajustarVista, limpiarBorrador });

/* ───────── Leyenda (solo cuenta lo que realmente está en el mapa) ───────── */
const conteo = computed(() => {
    const total: Record<ClaveVisual, number> = {
        registrado: 0,
        recolectado: 0,
        en_transito: 0,
        en_centro: 0,
        en_ruta: 0,
        entregado: 0,
        devuelto: 0,
        fallido: 0,
        desconocido: 0,
    };

    for (const envio of props.envios) {
        if (posicionEnvio(envio)) total[estadoVisual(envio.estado).clave]++;
    }

    return total;
});

const leyendaRutas = ESTADOS_DESPACHO.map((e) => ({
    estado: e,
    ...visualRuta(e),
}));
const sinEnvios = computed(() => listo.value && !props.envios.length);
</script>

<template>
    <div
        class="relative isolate h-full min-h-[440px] overflow-hidden rounded-lg border border-sidebar-border/70"
    >
        <div
            ref="contenedor"
            class="absolute inset-0"
            role="application"
            aria-label="Mapa de rutas y despachos"
        />

        <!-- Aviso cuando no hay envíos en el conjunto filtrado. -->
        <p
            v-if="sinEnvios"
            class="absolute top-3 left-1/2 z-[1000] -translate-x-1/2 rounded-lg border border-sidebar-border/70 bg-card/95 px-3 py-2 text-sm text-muted-foreground shadow-sm"
        >
            No hay envíos con los filtros seleccionados.
        </p>

        <div
            class="absolute bottom-3 left-3 z-[1000] max-w-[min(260px,calc(100%-1.5rem))] rounded-lg border border-sidebar-border/70 bg-card/95 text-xs shadow-md"
        >
            <button
                type="button"
                class="flex min-h-10 w-full items-center justify-between gap-3 rounded-lg px-3 font-semibold text-foreground transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                aria-controls="leyenda-mapa"
                :aria-expanded="leyendaAbierta"
                @click="leyendaAbierta = !leyendaAbierta"
            >
                <span>Leyenda del mapa</span>
                <svg
                    class="h-4 w-4 shrink-0 transition-transform"
                    :class="leyendaAbierta ? 'rotate-180' : ''"
                    viewBox="0 0 20 20"
                    fill="none"
                    aria-hidden="true"
                >
                    <path
                        d="m5 7.5 5 5 5-5"
                        stroke="currentColor"
                        stroke-width="1.7"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                    />
                </svg>
            </button>

            <div
                v-if="leyendaAbierta"
                id="leyenda-mapa"
                class="max-h-64 overflow-y-auto border-t border-sidebar-border/70 px-3 py-2.5"
            >
                <p class="mb-1.5 font-semibold text-foreground">
                    Envíos ubicados
                </p>
                <ul class="space-y-1">
                    <li
                        v-for="clave in ORDEN_VISUALES"
                        :key="clave"
                        class="flex items-center gap-2 text-foreground"
                    >
                        <span
                            class="h-3 w-3 shrink-0 rounded-full ring-2 ring-white"
                            :style="{ backgroundColor: VISUALES[clave].color }"
                        />
                        <span class="flex-1">{{
                            VISUALES[clave].etiqueta
                        }}</span>
                        <span class="text-muted-foreground tabular-nums">{{
                            conteo[clave]
                        }}</span>
                    </li>
                </ul>

                <p class="mt-2.5 mb-1.5 font-semibold text-foreground">Rutas</p>
                <ul class="space-y-1">
                    <li
                        v-for="r in leyendaRutas"
                        :key="r.estado"
                        class="flex items-center gap-2 text-foreground"
                    >
                        <span
                            class="w-5 shrink-0 border-t-4"
                            :style="{
                                borderColor: r.color,
                                borderTopStyle: r.discontinua
                                    ? 'dashed'
                                    : 'solid',
                            }"
                        />
                        <span>{{ r.etiqueta }}</span>
                    </li>
                </ul>

                <p
                    class="mt-2.5 flex items-center gap-2 border-t border-sidebar-border/70 pt-2 text-foreground"
                >
                    <span
                        class="flex h-4 w-4 shrink-0 items-center justify-center rounded-[4px] border border-white bg-slate-800"
                    />
                    Sucursal
                </p>
            </div>
        </div>
    </div>
</template>
