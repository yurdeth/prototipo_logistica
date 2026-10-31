<?php

namespace App\Http\Requests\Rutas;

use App\Models\TipoIncidencia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Registro de incidencias de un despacho. El despacho llega
 * implícito por la ruta; el envío afectado, cuando se indica, debe ser una
 * de las paradas del propio despacho.
 */
class StoreIncidenciaRequest extends FormRequest
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
            'tipo_incidencia_id' => [
                'required', 'integer',
                Rule::exists(TipoIncidencia::class, 'id_tipo'),
            ],
            'minutos_retraso' => ['required', 'integer', 'min:0', 'max:1440'],
            'envio_id' => ['nullable', 'integer'],
            'descripcion' => ['nullable', 'string', 'max:500'],
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

                $envioId = $this->filled('envio_id') ? (int) $this->input('envio_id') : null;

                if ($envioId === null) {
                    return;
                }

                $esDelDespacho = DB::table('rutas.despacho_paradas')
                    ->where('despacho_id', (int) $this->route('despacho'))
                    ->where('envio_id', $envioId)
                    ->exists();

                if (! $esDelDespacho) {
                    $validador->errors()->add(
                        'envio_id',
                        'El envío indicado no pertenece a las paradas de este despacho.'
                    );
                }
            },
        ];
    }
}
