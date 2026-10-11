<?php

namespace App\Http\Requests\Flota;

use App\Models\Vehiculo;
use Illuminate\Validation\Rule;

class UpdateVehiculoRequest extends StoreVehiculoRequest
{
    /**
     * Mismas reglas que el registro, pero la placa única ignora el vehículo
     * que se está editando.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $reglas = parent::rules();

        $vehiculo = $this->route('vehiculo');
        $idActual = $vehiculo instanceof Vehiculo ? $vehiculo->id_vehiculo : 0;

        $reglas['placa'] = [
            'required',
            'string',
            'max:15',
            'regex:'.self::FORMATO_PLACA,
            Rule::unique(Vehiculo::class, 'placa')->ignore($idActual),
        ];

        return $reglas;
    }
}
