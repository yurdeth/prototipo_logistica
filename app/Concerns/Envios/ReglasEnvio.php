<?php

namespace App\Concerns\Envios;

use Illuminate\Validation\Rule;

/**
 * Reglas y mensajes compartidos por StoreEnvioRequest y UpdateEnvioRequest,
 * para que ambos formularios validen exactamente lo mismo.
 */
trait ReglasEnvio
{
    public const PRIORIDADES = ['BAJA', 'NORMAL', 'URGENTE'];

    public const ESTADOS = ['REGISTRADO', 'RECOLECTADO', 'EN_TRANSITO', 'EN_CENTRO', 'EN_RUTA', 'ENTREGADO', 'DEVUELTO', 'FALLIDO'];

    /**
     * `exists:` de Laravel interpreta "envios.clientes" como conexión + tabla.
     * Anteponer el nombre de la conexión permite usar tablas con esquema de PostgreSQL.
     */
    protected function existe(string $tabla, string $columna): \Illuminate\Validation\Rules\Exists
    {
        return Rule::exists(config('database.default').'.'.$tabla, $columna);
    }

    protected function reglasComunes(): array
    {
        return [
            'cliente_remitente_id' => ['required', 'integer', $this->existe('envios.clientes', 'id_cliente')],
            'cliente_destinatario_id' => [
                'required', 'integer', 'different:cliente_remitente_id',
                $this->existe('envios.clientes', 'id_cliente'),
            ],
            'sucursal_origen_id' => [
                'required', 'integer',
                $this->existe('territorio.sucursales', 'id_sucursal')->where('activa', true),
            ],
            'territorio_destino_id' => ['required', 'integer', $this->existe('territorio.territorios', 'id_territorio')],
            'direccion_destino' => ['required', 'string', 'min:10', 'max:255'],
            'peso_kg' => ['required', 'numeric', 'gt:0', 'max:99999999.99', 'decimal:0,2'],
            'volumen_m3' => ['nullable', 'numeric', 'gt:0', 'max:99999999.99', 'decimal:0,2'],
            'prioridad' => ['required', Rule::in(self::PRIORIDADES)],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->direccion_destino)) {
            $this->merge(['direccion_destino' => trim(preg_replace('/\s+/', ' ', $this->direccion_destino))]);
        }
    }

    protected function mensajesComunes(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'integer' => 'El valor de :attribute no es válido.',
            'numeric' => 'El campo :attribute debe ser un número.',
            'date' => 'El campo :attribute no es una fecha válida.',
            'exists' => 'El valor elegido en :attribute no existe o no está disponible.',

            'cliente_destinatario_id.different' => 'El destinatario debe ser distinto al remitente.',
            'sucursal_origen_id.exists' => 'La sucursal de origen no existe o está inactiva.',
            'territorio_destino_id.exists' => 'El territorio de destino no existe.',

            'direccion_destino.min' => 'La dirección está incompleta: indica colonia, calle y número o referencia (mínimo :min caracteres).',
            'direccion_destino.max' => 'La dirección no puede superar :max caracteres.',

            'peso_kg.gt' => 'El peso debe ser mayor que cero.',
            'peso_kg.max' => 'El peso indicado es demasiado grande.',
            'peso_kg.decimal' => 'El peso admite como máximo 2 decimales.',
            'volumen_m3.gt' => 'El volumen debe ser mayor que cero. Déjalo vacío si no lo conoces.',
            'volumen_m3.max' => 'El volumen indicado es demasiado grande.',
            'volumen_m3.decimal' => 'El volumen admite como máximo 2 decimales.',

            'prioridad.in' => 'Elige una prioridad válida: baja, normal o urgente.',

            'latitud.between' => 'La latitud debe estar entre -90 y 90.',
            'longitud.between' => 'La longitud debe estar entre -180 y 180.',
            'latitud.required_with' => 'Si indicas la longitud, indica también la latitud.',
            'longitud.required_with' => 'Si indicas la latitud, indica también la longitud.',

            'fecha_comprometida.required' => 'Indica la fecha y hora comprometida de entrega.',
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
            'prioridad' => 'prioridad',
            'fecha_comprometida' => 'fecha comprometida',
            'latitud' => 'latitud',
            'longitud' => 'longitud',
            'estado' => 'estado',
            'nota' => 'nota',
        ];
    }
}