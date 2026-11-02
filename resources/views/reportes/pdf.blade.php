<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 24px 20px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1e293b; }
        h1 { font-size: 18px; margin: 0; }
        .subtitulo { color: #64748b; margin: 2px 0 0; font-size: 11px; }
        .meta { font-size: 10px; color: #475569; margin-top: 10px; padding: 8px 10px; background: #f1f5f9; border-radius: 6px; }
        .meta strong { color: #0f172a; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th { background: #0f2557; color: #fff; text-align: left; padding: 5px 7px; font-size: 9.5px; }
        td { border-bottom: 1px solid #e2e8f0; padding: 4px 7px; font-size: 9.5px; }
        tr:nth-child(even) td { background: #f8fafc; }
        .seccion { font-size: 13px; font-weight: bold; color: #0f2557; margin: 16px 0 2px; padding-bottom: 3px; border-bottom: 2px solid #0f2557; }
        .vacio { color: #94a3b8; font-style: italic; padding: 6px 0; }
        .vencido { color: #b91c1c; font-weight: bold; }
        .pronto { color: #b45309; font-weight: bold; }
        .plazo { color: #15803d; }
        .sin-valor { color: #94a3b8; }
        .pie { margin-top: 14px; font-size: 8.5px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    <h1>Reportes operativos — distribución logística de paquetería</h1>
    <p class="subtitulo">Envíos por vencer · Despachos según compromiso · Incidencias</p>

    <div class="meta">
        Generado: {{ $generado->format('d/m/Y H:i') }} (hora de El Salvador) &nbsp;·&nbsp;
        Situación de plazo:
        <strong>{{ $etiquetaPlazo }}</strong> &nbsp;·&nbsp;
        Tipo de incidencia:
        <strong>{{ $etiquetaTipo }}</strong> &nbsp;·&nbsp;
        Período de incidencias:
        <strong>{{ $etiquetaDias }}</strong>
    </div>

    @if (in_array('envios', $secciones))
    <p class="seccion">Envíos por vencer ({{ count($envios) }})</p>
    @if (count($envios))
        <table>
            <thead>
                <tr>
                    <th>Guía</th><th>Destino</th><th>Estado</th><th>Prioridad</th>
                    <th>Compromiso</th><th>Situación</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($envios as $e)
                    <tr>
                        <td>{{ $e->codigo_guia }}</td>
                        <td>{{ $e->destino }}</td>
                        <td>{{ strtolower(str_replace('_', ' ', $e->estado)) }}</td>
                        <td>{{ strtolower($e->prioridad) }}</td>
                        <td>{{ \Illuminate\Support\Carbon::parse($e->fecha_comprometida)->setTimezone('America/El_Salvador')->format('d/m/Y H:i') }}</td>
                        <td class="{{ $e->plazo === 'vencido' ? 'vencido' : ($e->plazo === 'vence_pronto' ? 'pronto' : 'plazo') }}">
                            {{ ['vencido' => 'Vencido', 'vence_pronto' => 'Vence pronto', 'en_plazo' => 'En plazo'][$e->plazo] ?? $e->plazo }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="vacio">Sin envíos en la situación seleccionada.</p>
    @endif
    @endif

    @if (in_array('despachos', $secciones))
    <p class="seccion">Despachos sin cerrar según compromiso ({{ count($despachos) }})</p>
    @if (count($despachos))
        <table>
            <thead>
                <tr>
                    <th>Despacho</th><th>Fecha</th><th>Sucursal</th><th>Estado</th>
                    <th>Paradas</th><th>Vencidas</th><th>Por vencer</th><th>En plazo</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($despachos as $d)
                    <tr>
                        <td>{{ $d['codigo'] }}</td>
                        <td>{{ $d['fecha'] }}</td>
                        <td>{{ $d['sucursal'] }}</td>
                        <td>{{ strtolower($d['estado']) }}</td>
                        <td>{{ $d['total'] }}</td>
                        <td class="{{ $d['vencidos'] > 0 ? 'vencido' : '' }}">{{ $d['vencidos'] }}</td>
                        <td class="{{ $d['vence_pronto'] > 0 ? 'pronto' : '' }}">{{ $d['vence_pronto'] }}</td>
                        <td>{{ $d['en_plazo'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="vacio">Sin despachos sin cerrar.</p>
    @endif
    @endif

    @if (in_array('incidencias', $secciones))
    <p class="seccion">Incidencias ({{ count($incidencias) }})</p>
    @if (count($porTipo))
        <table>
            <thead>
                <tr><th>Tipo</th><th>Total</th><th>Minutos de retraso acumulados</th></tr>
            </thead>
            <tbody>
                @foreach ($porTipo as $t)
                    <tr>
                        <td>{{ strtolower(str_replace('_', ' ', $t['tipo'])) }}</td>
                        <td>{{ $t['total'] }}</td>
                        <td>{{ $t['minutos'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif
    @if (count($incidencias))
        <table>
            <thead>
                <tr>
                    <th>Tipo</th><th>Despacho</th><th>Envío</th>
                    <th>Retraso (min)</th><th>Fecha</th><th>Descripción</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($incidencias as $i)
                    <tr>
                        <td>{{ strtolower(str_replace('_', ' ', $i->tipo)) }}</td>
                        <td>{{ $i->despacho_id ? 'DSP-'.str_pad((string) $i->despacho_id, 6, '0', STR_PAD_LEFT) : '—' }}</td>
                        <td>{{ $i->guia ?? '—' }}</td>
                        <td class="{{ $i->minutos_retraso > 0 ? 'vencido' : 'sin-valor' }}">{{ $i->minutos_retraso }}</td>
                        <td>{{ \Illuminate\Support\Carbon::parse($i->ocurrio_en)->setTimezone('America/El_Salvador')->format('d/m/Y H:i') }}</td>
                        <td>{{ $i->descripcion ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="vacio">Sin incidencias en el período seleccionado.</p>
    @endif
    @endif

    <p class="pie">
        Sistema de gestión operacional y analítica de distribución logística · Documento generado automáticamente con los filtros aplicados en pantalla.
    </p>
</body>
</html>
