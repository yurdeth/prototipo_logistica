<?php

namespace App\Http\Requests\Flota;

use App\Models\EstadoVehiculo;
use App\Models\MarcaVehiculo;
use App\Models\ModeloVehiculo;
use App\Models\Sucursal;
use App\Models\TipoVehiculo;
use App\Models\Vehiculo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVehiculoRequest extends FormRequest
{
    /**
     * Letras de categoría del formato nacional de placas de El Salvador:
     * P particular, C carga, M motocicleta, A transporte público, N no remunerado,
     * MB microbús y AB transporte colectivo, RE remolques, CD cuerpo diplomático
     * y CC cuerpo consular. La cantidad de dígitos varía según la emisión
     * (las placas antiguas son más cortas), entre 3 y 6.
     */
    protected const FORMATO_PLACA = '/^(P|C|M|A|N|MB|AB|RE|CD|CC)\d{3,6}$/';

    /**
     * Normaliza la placa antes de validar: mayúsculas, sin espacios ni guiones.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'placa' => strtoupper(preg_replace('/[\s-]/', '', (string) $this->input('placa'))),
        ]);
    }

    /**
     * Reglas del registro de vehículos. Rule::unique/exists con el modelo evita
     * que el esquema de la tabla ("flota", "territorio") se interprete como conexión.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'placa' => [
                'required',
                'string',
                'max:15',
                'regex:'.self::FORMATO_PLACA,
                Rule::unique(Vehiculo::class, 'placa'),
            ],
            'tipo_vehiculo_id' => ['required', 'integer', Rule::exists(TipoVehiculo::class, 'id_tipo_vehiculo')],
            'estado_vehiculo_id' => ['required', 'integer', Rule::exists(EstadoVehiculo::class, 'id_estado')],
            'sucursal_id' => ['required', 'integer', Rule::exists(Sucursal::class, 'id_sucursal')],
            'marca_vehiculo_id' => ['required', 'integer', Rule::exists(MarcaVehiculo::class, 'id_marca')],
            // El modelo debe existir y pertenecer a la marca seleccionada
            'modelo_vehiculo_id' => [
                'nullable',
                'integer',
                Rule::exists(ModeloVehiculo::class, 'id_modelo')->where(
                    fn ($consulta) => $consulta->where('marca_vehiculo_id', $this->integer('marca_vehiculo_id')),
                ),
            ],
            // Columnas de lectura en flota.vehiculos; el controlador las rellena desde el catálogo
            'marca' => ['nullable', 'string', 'max:60'],
            'modelo' => ['nullable', 'string', 'max:60'],
            // Mínimo 1990: coincide con el CHECK de la migración y cubre el parque
            // salvadoreño (edad promedio ~15-17 años, con unidades de más de 20 en circulación).
            // Máximo: el año en curso; un vehículo no puede ser de un año futuro.
            'anio' => ['nullable', 'integer', 'min:1990', 'max:'.date('Y')],
            'consumo_km_l' => ['nullable', 'numeric', 'min:0.01', 'max:999.99'],
        ];
    }

    /**
     * Mensajes de validación claros y en español para el formulario.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'placa.required' => 'La placa es obligatoria.',
            'placa.max' => 'La placa no puede tener más de 15 caracteres.',
            'placa.regex' => 'La placa no coincide con el formato nacional: letra de categoría (P, C, M, A, N, MB, AB, RE, CD o CC) seguida de 3 a 6 dígitos, por ejemplo P123456.',
            'placa.unique' => 'Ya existe un vehículo registrado con esa placa.',
            'tipo_vehiculo_id.required' => 'Selecciona el tipo de vehículo.',
            'tipo_vehiculo_id.exists' => 'El tipo de vehículo seleccionado no existe.',
            'estado_vehiculo_id.required' => 'Selecciona el estado del vehículo.',
            'estado_vehiculo_id.exists' => 'El estado seleccionado no existe.',
            'sucursal_id.required' => 'Selecciona la sucursal base del vehículo.',
            'sucursal_id.exists' => 'La sucursal seleccionada no existe.',
            'marca_vehiculo_id.required' => 'Selecciona la marca del vehículo.',
            'marca_vehiculo_id.exists' => 'La marca seleccionada no existe.',
            'modelo_vehiculo_id.exists' => 'El modelo seleccionado no pertenece a la marca indicada.',
            'marca.max' => 'La marca no puede tener más de 60 caracteres.',
            'modelo.max' => 'El modelo no puede tener más de 60 caracteres.',
            'anio.integer' => 'El año debe ser un número entero.',
            'anio.min' => 'El año debe ser 1990 o posterior.',
            'anio.max' => 'El año no puede ser posterior al año actual.',
            'consumo_km_l.numeric' => 'El consumo debe ser un número.',
            'consumo_km_l.min' => 'El consumo debe ser mayor a 0.',
            'consumo_km_l.max' => 'El consumo no puede superar 999.99.',
        ];
    }

    /**
     * Nombres de campo legibles para los mensajes de validación.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'consumo_km_l' => 'consumo',
            'anio' => 'año',
        ];
    }

    /**
     * El tipo y el modelo deben ser coherentes: un modelo de moto solo admite
     * el tipo Motocicleta, y un modelo de carga su tipo de catálogo.
     *
     * @return array<callable>
     */
    public function after(): array
    {
        return [
            function ($validator) {
                if (! $this->filled('modelo_vehiculo_id') || ! $this->filled('tipo_vehiculo_id')) {
                    return;
                }

                $coincide = ModeloVehiculo::query()
                    ->whereKey($this->integer('modelo_vehiculo_id'))
                    ->where('tipo_vehiculo_id', $this->integer('tipo_vehiculo_id'))
                    ->exists();

                if (! $coincide) {
                    $validator->errors()->add(
                        'tipo_vehiculo_id',
                        'El tipo seleccionado no corresponde al modelo elegido (moto o vehículo de carga).',
                    );
                }
            },
        ];
    }
}
