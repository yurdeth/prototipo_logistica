<?php

namespace App\Http\Requests\Envios;

use App\Concerns\Envios\ReglasEnvio;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEnvioRequest extends FormRequest
{
    use ReglasEnvio;

    // Verifica que el usuario tenga permiso para registrar envíos.
    public function authorize(): bool
    {
        return $this->user()?->can('envios.registrar') ?? false;
    }

    /** @return array<string, array<mixed>|string> */
    public function rules(): array
    {
        return [
            // Incluye las reglas de validación comunes para los envíos.
            ...$this->reglasComunes(),

            // La fecha comprometida debe ser válida y posterior al momento actual.
            'fecha_comprometida' => ['required', 'date', 'after:now'],

            // Un envío nuevo solo puede iniciar en estado REGISTRADO.
            'estado' => ['nullable', Rule::in(['REGISTRADO'])],

            // Las coordenadas son opcionales, pero deben enviarse ambas.
            'latitud' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitud'],
            'longitud' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitud'],
        ];
    }

    public function messages(): array
    {
        return [
            ...$this->mensajesComunes(),

            'fecha_comprometida.after' => 'La fecha comprometida debe ser posterior al momento actual.',
            'estado.in' => 'Un envío nuevo solo puede iniciar como REGISTRADO: no puede ingresar como entregado ni en otro estado.',
            'latitud.required_with' => 'Si indicas la longitud, también debes indicar la latitud.',
            'longitud.required_with' => 'Si indicas la latitud, también debes indicar la longitud.',
        ];
    }
}
