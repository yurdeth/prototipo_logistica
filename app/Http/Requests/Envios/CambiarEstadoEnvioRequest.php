<?php

namespace App\Http\Requests\Envios;

use App\Http\Controllers\Envios\EnvioController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CambiarEstadoEnvioRequest extends FormRequest
{
    // Cambiar el estado es una edición: mismo permiso que editar el envío.
    public function authorize(): bool
    {
        return $this->user()?->can('envios.editar') ?? false;
    }

    public function rules(): array
    {
        return [
            'estado' => ['required', Rule::in(EnvioController::ESTADOS)],
            'nota' => ['nullable', 'string', 'max:500'],
            'latitud' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitud'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitud'],
        ];
    }

    public function messages(): array
    {
        return [
            'estado.required' => 'Selecciona el nuevo estado del envío.',
            'estado.in' => 'El estado elegido no es válido.',
            'nota.max' => 'La nota no puede superar :max caracteres.',
            'numeric' => 'El campo :attribute debe ser un número.',
            'between' => 'El campo :attribute está fuera del rango válido.',
            'latitud.required_with' => 'Si indicas la longitud, también debes indicar la latitud.',
            'longitud.required_with' => 'Si indicas la latitud, también debes indicar la longitud.',
        ];
    }

    public function attributes(): array
    {
        return [
            'estado' => 'estado',
            'nota' => 'nota',
            'latitud' => 'latitud',
            'longitud' => 'longitud',
        ];
    }
}