<?php

namespace App\Concerns\Envios;

use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Reglas compartidas por StoreEnvioRequest y UpdateEnvioRequest.
 *
 * Nota: no se usa la regla `exists:envios.clientes,...` porque Laravel interpreta
 * "envios" como nombre de conexión, no como schema de PostgreSQL. Por eso la
 * existencia se comprueba con un closure sobre DB::table('schema.tabla').
 */
trait ReglasEnvio
{
    /** En el alta solo se aceptan sucursales activas; en la edición se permite una ya inactiva. */
    protected function exigeSucursalActiva(): bool
    {
        return true;
    }

    /** @return array<string, array<mixed>|string> */
    protected function reglasComunes(): array
    {
        $sucursal = $this->exigeSucursalActiva() ? ['activa' => true] : [];

        return [
            'cliente_remitente_id' => ['required', 'integer', $this->existe('envios.clientes', 'id_cliente')],
            'cliente_destinatario_id' => ['required', 'integer', $this->existe('envios.clientes', 'id_cliente')],
            'sucursal_origen_id' => ['required', 'integer', $this->existe('territorio.sucursales', 'id_sucursal', $sucursal)],
            'territorio_destino_id' => ['required', 'integer', $this->existe('territorio.territorios', 'id_territorio')],
            'direccion_destino' => ['required', 'string', 'min:10', 'max:255'],
            // Tope operativo: la carga no supera la capacidad del camión pesado más grande (18 000 kg, Tabla 3.2)
            'peso_kg' => ['required', 'numeric', 'gt:0', 'max:18000'],
            'volumen_m3' => ['nullable', 'numeric', 'gt:0', 'max:100'],
            'prioridad' => ['required', 'in:BAJA,NORMAL,URGENTE'],
        ];
    }

    /** @return array<string, string> */
    protected function mensajesComunes(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'required_with' => 'El campo :attribute es obligatorio cuando se indica la otra coordenada.',
            'integer' => 'El campo :attribute no es válido.',
            'numeric' => 'El campo :attribute debe ser un número.',
            'string' => 'El campo :attribute debe ser texto.',
            'gt' => 'El campo :attribute debe ser mayor que cero.',
            'between' => 'El campo :attribute está fuera del rango válido.',
            'max.numeric' => 'El campo :attribute excede el máximo permitido.',
            'max.string' => 'El campo :attribute no puede superar :max caracteres.',
            'in' => 'El valor de :attribute no es válido.',
            'date' => 'El campo :attribute debe ser una fecha válida.',
            'direccion_destino.min' => 'Escribe la dirección completa (colonia, calle, número o referencia).',
            'peso_kg.max' => 'El peso excede la capacidad del camión más grande de la flota (máximo 18 000 kg).',
            'volumen_m3.max' => 'El volumen no puede superar 100 m³.',
        ];
    }

    public function attributes(): array
    {
        return [
            'cliente_remitente_id' => 'remitente',
            'cliente_destinatario_id' => 'destinatario',
            'sucursal_origen_id' => 'sucursal de origen',
            'territorio_destino_id' => 'territorio de destino',
            'direccion_destino' => 'dirección de entrega',
            'peso_kg' => 'peso',
            'volumen_m3' => 'volumen',
            'fecha_comprometida' => 'fecha comprometida',
            'latitud' => 'latitud',
            'longitud' => 'longitud',
            'estado' => 'estado',
            'nota' => 'nota',
        ];
    }

    /** Regla "existe" compatible con tablas dentro de un schema PostgreSQL. */
    /** @param array<string, mixed> $extra */
    protected function existe(string $tabla, string $columna, array $extra = []): Closure
    {
        return function (string $atributo, mixed $valor, Closure $fail) use ($tabla, $columna, $extra): void {
            $existe = DB::table($tabla)->where($columna, $valor)->where($extra)->exists();

            if (! $existe) {
                $fail('El valor seleccionado en :attribute no existe o no está disponible.');
            }
        };
    }
}
