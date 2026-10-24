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
    type ParadaMapa,
    type SucursalMapa,
    type Visual,
    type VisualRuta,
} from '@/config/Rutas/Mapa';

// Mapa de rutas con Leaflet: sucursales, conexiones secuenciales y paradas.
const props = defineProps<{
    sucursales: SucursalMapa[];
    despachos: DespachoMapa[];
    envios: EnvioMapa[];
    seleccionado: number | null;
    paradaSeleccionadaId: number | null;
    mostrarConexiones: boolean;
}>();

const emit = defineEmits<{
    seleccionar: [id: number];
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
let capaSucursales: Leaflet.LayerGroup;
let capaRutas: Leaflet.LayerGroup;
let capaParadas: Leaflet.LayerGroup;
let observador: ResizeObserver | null = null;
let firmaBase = '';
let ajustado = false;

/* ───────── Posiciones ───────── */
type Coord = [number, number];

const posicion = (lat: number | null, lng: number | null): Coord | null => {
    if (lat === null || lng === null) return null;
    return [lat, lng];
};

const posicionParada = (p: ParadaMapa): Coord | null => posicion(p.lat, p.lng);

const posicionEnvio = (envio: EnvioMapa): Coord | null =>
    posicion(envio.lat, envio.lng);

const puntosSecuenciaRuta = (d: DespachoMapa): (Coord | null)[] => [
    d.origen ? [d.origen.lat, d.origen.lng] : null,
    ...d.paradas.map(posicionParada),
];

/* ───────── Iconos y popups ───────── */
const iconoParada = (
    v: Visual,
    orden: number | null,
    seleccionada: boolean,
    destinoFinal: boolean,
    ubicacionAproximada = false,
): Leaflet.DivIcon => {
    const borde = seleccionada
        ? '#0f172a'
        : ubicacionAproximada
          ? '#d97706'
          : destinoFinal
            ? '#7c3aed'
            : '#ffffff';

    return L.divIcon({
        className: 'marcador-parada',
        iconSize: [30, 38],
        iconAnchor: [15, 37],
        popupAnchor: [0, -34],
        html: `<svg width="30" height="38" viewBox="0 0 30 38" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <path d="M15 1C7.8 1 2 6.8 2 14c0 9.5 13 23 13 23s13-13.5 13-23C28 6.8 22.2 1 15 1z" fill="${v.color}" stroke="${borde}" stroke-width="${seleccionada || destinoFinal || ubicacionAproximada ? 3 : 2}" ${ubicacionAproximada ? 'stroke-dasharray="3 2"' : ''}/>
            <circle cx="15" cy="14" r="7" fill="#ffffff"/>
            <text x="15" y="17.5" text-anchor="middle" font-size="10" font-weight="700" font-family="system-ui,sans-serif" fill="#1f2937">${orden ?? '•'}</text>
        </svg>`,
    });
};

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

const popupParada = (parada: ParadaMapa): string =>
    `<div style="min-width:210px;font:13px/1.4 system-ui,sans-serif;color:#0f172a">
        <div style="font-weight:700;margin-bottom:6px;font-family:ui-monospace,monospace">${escapeHtml(parada.codigo_guia)}</div>
        ${fila('Parada', parada.orden)}
        ${fila('Destino', parada.destino)}
        ${fila('Dirección', parada.direccion)}
        ${fila('Estado', etiquetaEnvio(parada.estado_envio))}
        ${fila('Incidencias', parada.incidencias)}
        ${fila(
            'Ubicación',
            parada.ubicacion_aproximada
                ? 'Aproximada: referencia territorial o sucursales'
                : 'Última ubicación reportada',
        )}
    </div>`;

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

/* ───────── Dibujo de sucursales y rutas ───────── */
const dibujarBase = () => {
    const firma = JSON.stringify(props.sucursales);
    if (firma === firmaBase) return;
    firmaBase = firma;

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

const sincronizarRutas = () => {
    capaRutas.clearLayers();

    for (const d of props.despachos) {
        const v = visualRuta(d.estado);
        const esSeleccion = d.id === props.seleccionado;
        if (!props.mostrarConexiones) continue;

        const puntos = puntosSecuenciaRuta(d);
        const indiceParadaActual = esSeleccion
            ? d.paradas.findIndex(
                  (parada) => parada.envio_id === props.paradaSeleccionadaId,
              )
            : -1;
        const indiceTramoActual = indiceParadaActual;

        for (let indice = 0; indice < puntos.length - 1; indice++) {
            const inicio = puntos[indice];
            const fin = puntos[indice + 1];
            if (!inicio || !fin) continue;

            const esTramoActual = esSeleccion && indice === indiceTramoActual;
            const esTramoAnterior =
                esSeleccion &&
                indiceParadaActual >= 0 &&
                indice < indiceTramoActual;
            const color = esTramoActual
                ? colors.azulClaro
                : esTramoAnterior
                  ? colors.verde
                  : esSeleccion
                    ? colors.grisMedio
                    : v.color;
            const etiquetaTramo = esTramoActual
                ? 'Tramo de la parada seleccionada'
                : esTramoAnterior
                  ? 'Tramo anterior en la revisión'
                  : esSeleccion
                    ? 'Tramo pendiente en la revisión'
                    : v.etiqueta;

            const linea = L.polyline([inicio, fin], {
                color,
                weight: esTramoActual ? 7 : esSeleccion ? 4 : 3,
                opacity: esSeleccion ? 0.95 : 0.55,
                dashArray:
                    esSeleccion && !esTramoActual && !esTramoAnterior
                        ? '7 7'
                        : v.discontinua && !esSeleccion
                          ? '8 8'
                          : undefined,
                lineJoin: 'round',
            })
                .bindPopup(popupRuta(d, v), { maxWidth: 280 })
                .bindTooltip(
                    `${escapeHtml(d.codigo)} · ${escapeHtml(etiquetaTramo)}`,
                    { sticky: true },
                )
                .addTo(capaRutas);
            linea.on('click', () => emit('seleccionar', d.id));
        }
    }
};

const sincronizarEnvios = () => {
    capaParadas.clearLayers();
    const idsMarcados = new Set<number>();

    const ubicados = props.envios.filter(
        (envio) => envio.lat !== null && envio.lng !== null,
    );

    for (const envio of ubicados) {
        const pos = posicionEnvio(envio);
        if (!pos) continue;

        const despacho = props.despachos.find(
            (ruta) => ruta.id === envio.ruta_id,
        );
        const indiceParada =
            despacho?.paradas.findIndex(
                (parada) => parada.envio_id === envio.id,
            ) ?? -1;
        const seleccionada = envio.id === props.paradaSeleccionadaId;
        const destinoFinal =
            indiceParada >= 0 &&
            indiceParada === (despacho?.paradas.length ?? 0) - 1;
        idsMarcados.add(envio.id);
        const visual = estadoVisual(envio.estado);
        const marcador = L.marker(pos, {
            icon: iconoParada(visual, envio.orden, seleccionada, destinoFinal),
            title: `${seleccionada ? 'Parada seleccionada · ' : ''}${destinoFinal ? 'Destino final · ' : ''}${envio.codigo_guia} · ${etiquetaEnvio(envio.estado)}`,
            riseOnHover: true,
            zIndexOffset: seleccionada ? 1000 : 0,
            opacity:
                props.seleccionado !== null &&
                envio.ruta_id !== props.seleccionado &&
                !seleccionada
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

    const rutaSeleccionada = props.despachos.find(
        (despacho) => despacho.id === props.seleccionado,
    );
    if (!rutaSeleccionada) return;

    for (const parada of rutaSeleccionada.paradas) {
        if (idsMarcados.has(parada.envio_id)) continue;
        const pos = posicionParada(parada);
        if (!pos) continue;

        const seleccionada = parada.envio_id === props.paradaSeleccionadaId;
        const destinoFinal =
            rutaSeleccionada.paradas[rutaSeleccionada.paradas.length - 1]
                ?.envio_id === parada.envio_id;
        const marcador = L.marker(pos, {
            icon: iconoParada(
                estadoVisual(parada.estado_envio),
                parada.orden,
                seleccionada,
                destinoFinal,
                parada.ubicacion_aproximada,
            ),
            title: `${seleccionada ? 'Parada seleccionada · ' : ''}${destinoFinal ? 'Destino final · ' : ''}${parada.ubicacion_aproximada ? 'Ubicación territorial aproximada · ' : ''}${parada.codigo_guia ?? ''}`,
            riseOnHover: true,
            zIndexOffset: seleccionada ? 1000 : 0,
        })
            .bindPopup(popupParada(parada), { maxWidth: 300 })
            .addTo(capaParadas);
        marcador.on('click', () => emit('seleccionar', rutaSeleccionada.id));
    }
};

/** Centra el mapa en lo que hay: la selección, o todas las rutas y marcadores visibles. */
const ajustarVista = () => {
    if (!mapa) return;

    const sel = props.seleccionado;
    const rutaSeleccionada = props.despachos.find(
        (despacho) => despacho.id === sel,
    );
    if (rutaSeleccionada && props.paradaSeleccionadaId !== null) {
        const paradaRuta = rutaSeleccionada.paradas.find(
            (parada) => parada.envio_id === props.paradaSeleccionadaId,
        );
        let posicionSeleccionada = paradaRuta
            ? posicionParada(paradaRuta)
            : null;
        if (!posicionSeleccionada) {
            const envioSeleccionado = props.envios.find(
                (envio) => envio.id === props.paradaSeleccionadaId,
            );
            posicionSeleccionada = envioSeleccionado
                ? posicionEnvio(envioSeleccionado)
                : null;
        }

        if (posicionSeleccionada) {
            mapa.flyTo(posicionSeleccionada, Math.max(mapa.getZoom(), 12), {
                animate: true,
                duration: 0.8,
                easeLinearity: 0.25,
            });
            return;
        }

        const puntosRuta = rutaSeleccionada.paradas.flatMap((parada) => {
            const punto = posicionParada(parada);
            return punto ? [punto] : [];
        });
        if (puntosRuta.length) {
            mapa.fitBounds(L.latLngBounds(puntosRuta), {
                padding: [48, 48],
                maxZoom: 14,
                animate: true,
                duration: 0.8,
            });
            return;
        }
    }

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
        puntos.push(
            ...lista.flatMap((despacho) =>
                puntosSecuenciaRuta(despacho).filter(
                    (punto): punto is Coord => punto !== null,
                ),
            ),
        );
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

/* ───────── Ciclo de vida ───────── */
onMounted(async () => {
    // Leaflet se carga solo al abrir esta vista (no pesa en el resto de la aplicación).
    const modulo = await import('leaflet');
    L = ((modulo as unknown as { default?: typeof Leaflet }).default ??
        modulo) as typeof Leaflet;

    if (!contenedor.value) return; // la vista se cerró mientras se cargaba

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

    capaRutas = L.layerGroup().addTo(mapa);
    capaSucursales = L.layerGroup().addTo(mapa);
    capaParadas = L.layerGroup().addTo(mapa);

    // El mapa se redimensiona con su contenedor (por ejemplo, al cambiar el ancho de la pantalla).
    observador = new ResizeObserver(() => mapa?.invalidateSize());
    observador.observe(contenedor.value);

    listo.value = true;
    sincronizar();
});

onBeforeUnmount(() => {
    observador?.disconnect();
    mapa?.remove();
    mapa = null;
});

watch(
    [
        () => props.despachos,
        () => props.envios,
        () => props.sucursales,
        () => props.seleccionado,
        () => props.paradaSeleccionadaId,
        () => props.mostrarConexiones,
    ],
    () => listo.value && sincronizar(),
);

// Al elegir un despacho, el mapa se acerca a su ruta; al quitar la selección, vuelve a mostrar todo.
watch(
    [() => props.seleccionado, () => props.paradaSeleccionadaId],
    async () => {
        if (!listo.value) return;
        await nextTick();
        ajustarVista();
    },
);

defineExpose({ ajustarVista });

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
    const idsContados = new Set<number>();

    for (const envio of props.envios) {
        if (!posicionEnvio(envio)) continue;
        total[estadoVisual(envio.estado).clave]++;
        idsContados.add(envio.id);
    }

    const rutaSeleccionada = props.despachos.find(
        (despacho) => despacho.id === props.seleccionado,
    );
    for (const parada of rutaSeleccionada?.paradas ?? []) {
        if (idsContados.has(parada.envio_id) || !posicionParada(parada)) {
            continue;
        }
        total[estadoVisual(parada.estado_envio).clave]++;
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
    <div class="relative isolate h-full overflow-hidden">
        <div
            ref="contenedor"
            class="absolute inset-0"
            role="application"
            aria-label="Mapa de rutas y despachos"
        />

        <!-- Aviso cuando no hay envíos en el conjunto filtrado. -->
        <p
            v-if="sinEnvios"
            class="absolute top-14 left-1/2 z-[1000] w-max max-w-[calc(100%-1.5rem)] -translate-x-1/2 rounded-lg border border-sidebar-border/70 bg-card/95 px-3 py-2 text-center text-sm text-muted-foreground shadow-sm"
        >
            No hay envíos con los filtros seleccionados.
        </p>

        <!-- Leyenda: arriba a la derecha para no chocar con la tarjeta de parada -->
        <div
            class="absolute top-3 right-3 z-[1000] max-w-[calc(100%-4.5rem)] rounded-lg border border-sidebar-border/70 bg-card/95 text-xs shadow-md backdrop-blur"
            :class="leyendaAbierta ? 'w-64' : ''"
        >
            <button
                type="button"
                class="flex min-h-10 w-full items-center justify-between gap-3 rounded-lg px-3 font-semibold text-foreground transition-colors hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                aria-controls="leyenda-mapa"
                :aria-expanded="leyendaAbierta"
                @click="leyendaAbierta = !leyendaAbierta"
            >
                <span>Leyenda</span>
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
                class="max-h-[min(22rem,50vh)] space-y-3 overflow-y-auto border-t border-sidebar-border/70 px-3 py-3"
            >
                <section>
                    <h3 class="mb-1.5 font-semibold text-foreground">
                        Envíos y paradas
                    </h3>
                    <ul class="space-y-1">
                        <li
                            v-for="clave in ORDEN_VISUALES"
                            :key="clave"
                            class="flex items-center gap-2 text-foreground"
                        >
                            <span
                                class="h-3 w-3 shrink-0 rounded-full ring-2 ring-white"
                                :style="{
                                    backgroundColor: VISUALES[clave].color,
                                }"
                            />
                            <span class="flex-1">{{
                                VISUALES[clave].etiqueta
                            }}</span>
                            <span class="text-muted-foreground tabular-nums">{{
                                conteo[clave]
                            }}</span>
                        </li>
                    </ul>
                    <p class="mt-2 flex items-center gap-2 text-foreground">
                        <span
                            class="h-3 w-3 shrink-0 rounded-full border-2 border-dashed border-amber-600"
                        />
                        Centro del territorio (aproximado)
                    </p>
                    <p class="mt-1 flex items-center gap-2 text-foreground">
                        <span
                            class="flex h-3 w-3 shrink-0 items-center justify-center rounded-[3px] border border-white bg-slate-800"
                        />
                        Sucursal
                    </p>
                </section>

                <section class="border-t border-sidebar-border/70 pt-3">
                    <h3 class="mb-1.5 font-semibold text-foreground">Rutas</h3>
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
                </section>

                <section
                    v-if="mostrarConexiones"
                    class="border-t border-sidebar-border/70 pt-3"
                >
                    <h3 class="mb-1.5 font-semibold text-foreground">
                        Seguimiento de conexiones
                    </h3>
                    <ul class="space-y-1">
                        <li
                            v-for="tramo in [
                                {
                                    etiqueta: 'Tramo anterior en la revisión',
                                    color: colors.verde,
                                    discontinuo: false,
                                },
                                {
                                    etiqueta: 'Tramo de la parada seleccionada',
                                    color: colors.azulClaro,
                                    discontinuo: false,
                                },
                                {
                                    etiqueta: 'Tramo pendiente en la revisión',
                                    color: colors.grisMedio,
                                    discontinuo: true,
                                },
                            ]"
                            :key="tramo.etiqueta"
                            class="flex items-center gap-2 text-foreground"
                        >
                            <span
                                class="w-5 shrink-0 border-t-4"
                                :style="{
                                    borderColor: tramo.color,
                                    borderTopStyle: tramo.discontinuo
                                        ? 'dashed'
                                        : 'solid',
                                }"
                            />
                            <span>{{ tramo.etiqueta }}</span>
                        </li>
                    </ul>
                    <p class="mt-2 text-muted-foreground">
                        Las paradas aproximadas usan el punto representativo de
                        su territorio; las líneas no representan trazado vial ni
                        GPS.
                    </p>
                </section>
            </div>
        </div>
    </div>
</template>
