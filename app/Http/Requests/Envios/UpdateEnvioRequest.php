<?php

namespace App\Http\Requests\Envios;

use App\Http\Controllers\Envios\EnvioController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UpdateEnvioRequest extends FormRequest
{
    /**
     * Verifica que el usuario tenga permiso para editar envíos.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('envios.editar') ?? false;
    }

    /**
     * Normaliza la dirección antes de validar (sin espacios de más).
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->direccion_destino)) {
            $this->merge([
                'direccion_destino' => trim(preg_replace('/\s+/', ' ', $this->direccion_destino)),
            ]);
        }
    }

    /**
     * Comprueba que un registro exista consultando directamente `esquema.tabla`.
     *
     * No se usa la regla `exists:` de Laravel porque toma el texto antes del primer
     * punto como nombre de conexión ("envios.clientes" => conexión "envios").
     */
    protected function existe(string $tabla, string $columna, string $mensaje): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($tabla, $columna, $mensaje): void {
            if (! DB::table($tabla)->where($columna, $value)->exists()) {
                $fail($mensaje);
            }
        };
    }

    /**
     * Reglas de validación para actualizar un envío.
     *
     * Al editar se permite que la sucursal de origen ya no esté activa
     * (el envío pudo registrarse antes de que se desactivara).
     */
    public function rules(): array
    {
        return [
            'cliente_remitente_id' => [
                'required',
                'integer',
                $this->existe('envios.clientes', 'id_cliente', 'El cliente remitente seleccionado no existe.'),
            ],

            'cliente_destinatario_id' => [
                'required',
                'integer',
                'different:cliente_remitente_id',
                $this->existe('envios.clientes', 'id_cliente', 'El cliente destinatario seleccionado no existe.'),
            ],

            'sucursal_origen_id' => [
                'required',
                'integer',
                $this->existe('territorio.sucursales', 'id_sucursal', 'La sucursal seleccionada no existe.'),
            ],

            'direccion_destino' => [
                'required',
                'string',
                'min:10',
                'max:255',
            ],

            'territorio_destino_id' => [
                'required',
                'integer',
                $this->existe('territorio.territorios', 'id_territorio', 'El territorio seleccionado no existe.'),
            ],

            'peso_kg' => [
                'required',
                'numeric',
                'gt:0',
                'max:99999999.99',
                'decimal:0,2',
            ],

            'volumen_m3' => [
                'nullable',
                'numeric',
                'gt:0',
                'max:99999999.99',
                'decimal:0,2',
            ],

            'prioridad' => [
                'required',
                Rule::in(['BAJA', 'NORMAL', 'URGENTE']),
            ],

            'fecha_comprometida' => [
                'required',
                'date',
                function (
                    string $attribute,
                    mixed $value,
                    \Closure $fail
                ): void {
                    $registro = DB::table('envios.envios')
                        ->where('id_envio', $this->route('envio'))
                        ->value('fecha_registro');

                    if (
                        $registro &&
                        Carbon::parse($value)->lte(Carbon::parse($registro))
                    ) {
                        $fail(
                            'La fecha comprometida debe ser posterior a la fecha de registro del envío.'
                        );
                    }
                },
            ],

            'estado' => [
                'required',
                Rule::in(EnvioController::ESTADOS),
            ],

            'nota' => [
                'nullable',
                'string',
                'max:500',
            ],
        ];
    }

    /**
     * Mensajes personalizados de validación.
     * (Los de existencia de clientes, sucursal y territorio van dentro de existe().)
     */
    public function messages(): array
    {
        return [
            'cliente_remitente_id.required' => 'Selecciona el cliente remitente.',
            'cliente_remitente_id.integer' => 'El cliente remitente no es válido.',

            'cliente_destinatario_id.required' => 'Selecciona el cliente destinatario.',
            'cliente_destinatario_id.integer' => 'El cliente destinatario no es válido.',
            'cliente_destinatario_id.different' => 'El remitente y el destinatario deben ser diferentes.',

            'sucursal_origen_id.required' => 'Selecciona la sucursal de origen.',
            'sucursal_origen_id.integer' => 'La sucursal de origen no es válida.',

            'direccion_destino.required' => 'La dirección de destino es obligatoria.',
            'direccion_destino.min' => 'La dirección está incompleta: indica colonia, calle y número o referencia (mínimo :min caracteres).',
            'direccion_destino.max' => 'La dirección no puede superar :max caracteres.',

            'territorio_destino_id.required' => 'Selecciona el territorio de destino.',
            'territorio_destino_id.integer' => 'El territorio de destino no es válido.',

            'peso_kg.required' => 'El peso es obligatorio.',
            'peso_kg.numeric' => 'El peso debe ser numérico.',
            'peso_kg.gt' => 'El peso debe ser mayor que cero.',
            'peso_kg.max' => 'El peso indicado es demasiado grande.',
            'peso_kg.decimal' => 'El peso admite como máximo 2 decimales.',

            'volumen_m3.numeric' => 'El volumen debe ser numérico.',
            'volumen_m3.gt' => 'El volumen debe ser mayor que cero. Déjalo vacío si no lo conoces.',
            'volumen_m3.max' => 'El volumen indicado es demasiado grande.',
            'volumen_m3.decimal' => 'El volumen admite como máximo 2 decimales.',

            'prioridad.required' => 'Selecciona la prioridad.',
            'prioridad.in' => 'La prioridad seleccionada no es válida.',

            'fecha_comprometida.required' => 'La fecha comprometida es obligatoria.',
            'fecha_comprometida.date' => 'La fecha comprometida no es válida.',

            'estado.required' => 'Selecciona el estado del envío.',
            'estado.in' => 'El estado elegido no es válido.',

            'nota.max' => 'La nota no puede superar :max caracteres.',
        ];
    }
}