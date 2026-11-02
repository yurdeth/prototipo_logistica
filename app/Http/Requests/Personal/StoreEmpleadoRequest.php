<?php

namespace App\Http\Requests\Personal;

use App\Models\Departamento;
use App\Models\Empleado;
use App\Models\Puesto;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEmpleadoRequest extends FormRequest
{
    /**
     * Reglas del registro de empleados. Rule::unique/exists con el modelo evita
     * que el esquema de la tabla ("personal", "territorio") se interprete como
     * conexión de base de datos.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    /** Recorta espacios: una licencia en blanco o solo espacios queda vacía y falla como obligatoria. */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'numero_licencia' => trim((string) $this->input('numero_licencia')),
        ]);
    }

    public function rules(): array
    {
        return [
            // Cuenta del sistema asociada al empleado (opcional y única)
            'usuario_id' => [
                'nullable',
                'integer',
                Rule::exists(User::class, 'id'),
                Rule::unique(Empleado::class, 'usuario_id'),
            ],
            'puesto_id' => ['required', 'integer', Rule::exists(Puesto::class, 'id_puesto')],
            'sucursal_id' => ['required', 'integer', Rule::exists(Sucursal::class, 'id_sucursal')],
            // departamento_id no es columna de empleados: el formulario lo usa para
            // filtrar los puestos; se valida que el puesto elegido le pertenezca
            'departamento_id' => ['nullable', 'integer', Rule::exists(Departamento::class, 'id_departamento')],
            'nombres' => ['required', 'string', 'max:100'],
            'apellidos' => ['required', 'string', 'max:100'],
            'documento_identidad' => [
                'required',
                'string',
                'max:20',
                Rule::unique(Empleado::class, 'documento_identidad'),
            ],
            'telefono' => ['nullable', 'string', 'max:20'],
            // La licencia identifica al conductor: obligatoria y nunca repetida
            'numero_licencia' => [
                'required',
                'string',
                'max:30',
                Rule::unique(Empleado::class, 'numero_licencia'),
            ],
            // Un empleado no puede contratarse en el futuro
            'fecha_contratacion' => ['nullable', 'date', 'before_or_equal:today'],
            'activo' => ['boolean'],
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
            'usuario_id.exists' => 'El usuario seleccionado no existe.',
            'usuario_id.unique' => 'Esa cuenta de usuario ya está asignada a otro empleado.',
            'puesto_id.required' => 'Selecciona el puesto del empleado.',
            'puesto_id.exists' => 'El puesto seleccionado no existe.',
            'sucursal_id.required' => 'Selecciona la sucursal donde labora el empleado.',
            'sucursal_id.exists' => 'La sucursal seleccionada no existe.',
            'departamento_id.exists' => 'El departamento seleccionado no existe.',
            'nombres.required' => 'Los nombres son obligatorios.',
            'nombres.max' => 'Los nombres no pueden superar 100 caracteres.',
            'apellidos.required' => 'Los apellidos son obligatorios.',
            'apellidos.max' => 'Los apellidos no pueden superar 100 caracteres.',
            'documento_identidad.required' => 'El documento de identidad es obligatorio.',
            'documento_identidad.max' => 'El documento de identidad no puede superar 20 caracteres.',
            'documento_identidad.unique' => 'Ya existe un empleado registrado con ese documento de identidad.',
            'telefono.max' => 'El teléfono no puede superar 20 caracteres.',
            'numero_licencia.required' => 'El número de licencia es obligatorio.',
            'numero_licencia.unique' => 'Ya existe un empleado registrado con ese número de licencia.',
            'numero_licencia.max' => 'El número de licencia no puede superar 30 caracteres.',
            'fecha_contratacion.date' => 'La fecha de contratación no es válida.',
            'fecha_contratacion.before_or_equal' => 'La fecha de contratación no puede ser futura.',
        ];
    }

    /**
     * Nombres de campo legibles para los mensajes genéricos.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'documento_identidad' => 'documento de identidad',
            'numero_licencia' => 'número de licencia',
            'fecha_contratacion' => 'fecha de contratación',
        ];
    }

    /**
     * Coherencia formulario: si se elige departamento, el puesto debe
     * pertenecer a ese departamento.
     *
     * @return array<callable>
     */
    public function after(): array
    {
        return [
            function ($validator) {
                if (! $this->filled('departamento_id') || ! $this->filled('puesto_id')) {
                    return;
                }

                $coincide = Puesto::query()
                    ->whereKey($this->integer('puesto_id'))
                    ->where('departamento_id', $this->integer('departamento_id'))
                    ->exists();

                if (! $coincide) {
                    $validator->errors()->add(
                        'puesto_id',
                        'El puesto seleccionado no pertenece al departamento indicado.',
                    );
                }
            },
        ];
    }
}
