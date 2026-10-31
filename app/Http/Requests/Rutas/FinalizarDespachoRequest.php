<?php

namespace App\Http\Requests\Rutas;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

/**
 * Cierre de la ejecución de un despacho: kilómetros recorridos no negativos
 * y hora real de cada parada no anterior a su estimada. Solo se actualizan
 * las paradas del propio despacho.
 */
class FinalizarDespachoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // el gate despachos.gestionar protege la ruta
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'km_recorridos' => ['nullable', 'numeric', 'gte:0', 'max:10000'],
            'paradas' => ['nullable', 'array', 'max:20'],
            'paradas.*.id_parada' => ['required_with:paradas', 'integer'],
            'paradas.*.hora_real' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validador): void {
                if ($validador->errors()->any()) {
                    return;
                }

                $despachoId = (int) $this->route('despacho');

                foreach ((array) $this->input('paradas', []) as $parada) {
                    $modelo = DB::table('rutas.despacho_paradas')
                        ->where('id_parada', (int) ($parada['id_parada'] ?? 0))
                        ->where('despacho_id', $despachoId)
                        ->first();

                    if ($modelo === null) {
                        $validador->errors()->add(
                            'paradas',
                            'Una de las paradas indicadas no pertenece a este despacho.'
                        );

                        continue;
                    }

                    if (empty($parada['hora_real']) || $modelo->hora_estimada === null) {
                        continue;
                    }

                    // Las horas de parada son reloj de pared: la sesión de
                    // PostgreSQL las interpreta en hora local, así que la
                    // comparación es pared contra pared.
                    $real = Carbon::parse($parada['hora_real'])->format('Y-m-d H:i:s');
                    $estimada = Carbon::parse($modelo->hora_estimada)->format('Y-m-d H:i:s');

                    if ($real < $estimada) {
                        $validador->errors()->add(
                            "paradas.{$modelo->id_parada}.hora_real",
                            'La hora real de llegada no puede ser anterior a la hora estimada.'
                        );
                    }
                }
            },
        ];
    }
}
