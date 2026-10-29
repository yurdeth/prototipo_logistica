<?php

namespace App\Http\Requests\Personal;

use App\Models\Empleado;
use App\Models\User;
use Illuminate\Validation\Rule;

class UpdateEmpleadoRequest extends StoreEmpleadoRequest
{
    /**
     * Mismas reglas que el registro, pero documento, licencia y cuenta de
     * usuario únicos ignoran al empleado que se está editando.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $reglas = parent::rules();

        $empleado = $this->route('empleado');
        $idActual = $empleado instanceof Empleado ? $empleado->id_empleado : 0;

        $reglas['documento_identidad'] = [
            'required',
            'string',
            'max:20',
            Rule::unique(Empleado::class, 'documento_identidad')->ignore($idActual),
        ];
        $reglas['numero_licencia'] = [
            'nullable',
            'string',
            'max:30',
            Rule::unique(Empleado::class, 'numero_licencia')->ignore($idActual),
        ];
        $reglas['usuario_id'] = [
            'nullable',
            'integer',
            Rule::exists(User::class, 'id'),
            Rule::unique(Empleado::class, 'usuario_id')->ignore($idActual),
        ];

        return $reglas;
    }
}
