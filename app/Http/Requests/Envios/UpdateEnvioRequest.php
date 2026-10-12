<?php

namespace App\Http\Requests\Envios;

use App\Concerns\Envios\ReglasEnvio;
use App\Http\Controllers\Envios\EnvioController;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UpdateEnvioRequest extends FormRequest
{
    use ReglasEnvio;

    // Verifica que el usuario tenga permiso para editar envíos.
    public function authorize(): bool
    {
        return $this->user()?->can('envios.editar') ?? false;
    }

    // Al editar se permite que la sucursal de origen ya no esté activa.
    protected function exigeSucursalActiva(): bool
    {
        return false;
    }

    /** @return array<string, array<mixed>|string> */
    public function rules(): array
    {
        return [
            ...$this->reglasComunes(),

            'fecha_comprometida' => [
                'required',
                'date',

                // La BD exige fecha_comprometida > fecha_registro (no > ahora:
                // un envío antiguo puede tener un plazo que ya pasó).
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $registro = DB::table('envios.envios')
                        ->where('id_envio', $this->route('envio'))
                        ->value('fecha_registro');

                    if ($registro && Carbon::parse($value)->lte(Carbon::parse($registro))) {
                        $fail('La fecha comprometida debe ser posterior a la fecha de registro del envío.');
                    }
                },
            ],

            // El estado es obligatorio y debe pertenecer a los estados permitidos.
            'estado' => ['required', Rule::in(EnvioController::ESTADOS)],

            // La nota es opcional, debe ser texto y no superar 500 caracteres.
            'nota' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            ...$this->mensajesComunes(),

            'estado.required' => 'Selecciona el estado del envío.',
            'estado.in' => 'El estado elegido no es válido.',
            'nota.max' => 'La nota no puede superar :max caracteres.',
        ];
    }
}
