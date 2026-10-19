<?php

namespace App\Http\Requests\Rutas;

use Closure;
use Illuminate\Foundation\Http\FormRequest;

class GuardarZonaRequest extends FormRequest
{
    private const MAX_VERTICES = 500;

    public function authorize(): bool
    {
        return $this->user()?->can('rutas.zonas') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'geometry' => ['required', 'array', $this->poligonoValido()],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'geometry.required' => 'Dibuja la zona en el mapa antes de guardarla.',
            'geometry.array' => 'La zona enviada no es válida.',
        ];
    }

    /**
     * Polígono reconstruido solo con números validados: es lo único que se envía a PostGIS.
     *
     * @return array{type: string, coordinates: array<int, array<int, array<int, float>>>}
     */
    public function geometriaLimpia(): array
    {
        /** @var array<int, array<int, array<int, mixed>>> $anillos */
        $anillos = $this->input('geometry.coordinates', []);

        return [
            'type' => 'Polygon',
            'coordinates' => [
                array_map(
                    fn (array $p): array => [(float) $p[0], (float) $p[1]],
                    $anillos[0] ?? []
                ),
            ],
        ];
    }

    private function poligonoValido(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $fail): void {
            if (! is_array($valor) || ($valor['type'] ?? null) !== 'Polygon') {
                $fail('La zona debe ser un polígono.');

                return;
            }

            $anillos = $valor['coordinates'] ?? null;

            if (! is_array($anillos) || count($anillos) !== 1 || ! is_array($anillos[0])) {
                $fail('La zona debe tener un solo contorno, sin huecos.');

                return;
            }

            $anillo = $anillos[0];

            if (count($anillo) < 4 || count($anillo) > self::MAX_VERTICES + 1) {
                $fail('La zona debe tener entre 3 y '.self::MAX_VERTICES.' puntos.');

                return;
            }

            foreach ($anillo as $punto) {
                $enRango = is_array($punto)
                    && isset($punto[0], $punto[1])
                    && is_numeric($punto[0]) && is_numeric($punto[1])
                    && abs((float) $punto[0]) <= 180 && abs((float) $punto[1]) <= 90;

                if (! $enRango) {
                    $fail('La zona tiene coordenadas fuera de rango.');

                    return;
                }
            }

            $primero = $anillo[0];
            $ultimo = $anillo[count($anillo) - 1];

            if ((float) $primero[0] !== (float) $ultimo[0] || (float) $primero[1] !== (float) $ultimo[1]) {
                $fail('El contorno de la zona no está cerrado.');
            }
        };
    }
}
