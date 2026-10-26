<?php

namespace App\Http\Requests\Rutas;

use App\Models\Despacho;
use App\Models\DespachoParada;
use App\Models\Empleado;
use App\Models\Envio;
use App\Models\Sucursal;
use App\Models\Vehiculo;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreDespachoRequest extends FormRequest
{
    // Programar despachos exige el mismo perfil que registrar envíos.
    public function authorize(): bool
    {
        return $this->user()?->can('despachos.gestionar') ?? false;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'sucursal_origen_id' => ['required', 'integer', Rule::exists(Sucursal::class, 'id_sucursal')->where('activa', true)],
            'conductor_empleado_id' => [
                'required', 'integer',
                Rule::exists(Empleado::class, 'id_empleado')->where('activo', true),
                // sin licencia no puede salir a reparto
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $licencia = DB::table('personal.empleados')->where('id_empleado', $value)->value('numero_licencia');

                    if ($licencia === null || $licencia === '') {
                        $fail('El conductor seleccionado no tiene licencia registrada.');
                    }
                },
            ],
            'vehiculo_id' => [
                'required', 'integer',
                Rule::exists(Vehiculo::class, 'id_vehiculo'),
                // un vehículo inactivo no sale a reparto
                function (string $attribute, mixed $value, \Closure $fail): void {
                    $inactivo = DB::table('flota.vehiculos as v')
                        ->join('flota.estados_vehiculos as e', 'e.id_estado', '=', 'v.estado_vehiculo_id')
                        ->where('v.id_vehiculo', $value)
                        ->where('e.nombre_estado', 'INACTIVO')
                        ->exists();

                    if ($inactivo) {
                        $fail('El vehículo seleccionado está inactivo.');
                    }
                },
            ],
            'fecha' => ['required', 'date'],
            'km_planificados' => ['nullable', 'numeric', 'gt:0', 'max:99999999.99'],
            'costo_estimado' => ['nullable', 'numeric', 'gte:0', 'max:99999999.99'],

            'paradas' => ['required', 'array', 'min:1', 'max:20'],
            'paradas.*.envio_id' => ['required', 'integer', Rule::exists(Envio::class, 'id_envio'), 'distinct'],
            'paradas.*.orden' => ['required', 'integer', 'min:1', 'distinct'],
            'paradas.*.hora_estimada' => ['nullable', 'date'],
        ];
    }

    /**
     * Reglas que cruzan filas: cada envío solo va en un despacho, los órdenes
     * no se repiten y el conductor/vehículo no pueden estar doblemente
     * reservados ese día (los despachos completados liberan sus recursos).
     *
     * @return array<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $v): void {
            if ($v->errors()->isNotEmpty()) {
                return;
            }

            $enviosYaDespachados = DespachoParada::query()
                ->whereIn('envio_id', collect((array) $this->input('paradas'))->pluck('envio_id'))
                ->pluck('envio_id');

            if ($enviosYaDespachados->isNotEmpty()) {
                $guias = Envio::query()->whereIn('id_envio', $enviosYaDespachados)->pluck('codigo_guia')->implode(', ');
                $v->errors()->add('paradas', "Estos envíos ya pertenecen a otro despacho: {$guias}.");
            }

            $enviosDeParadas = DB::table('envios.envios')
                ->whereIn('id_envio', collect((array) $this->input('paradas'))->pluck('envio_id'))
                ->get(['id_envio', 'codigo_guia', 'peso_kg', 'sucursal_origen_id']);

            // La carga del despacho no puede superar lo que el tipo de vehículo admite.
            $capacidadKg = (float) DB::table('flota.vehiculos as v')
                ->join('flota.tipos_vehiculos as t', 't.id_tipo_vehiculo', '=', 'v.tipo_vehiculo_id')
                ->where('v.id_vehiculo', $this->input('vehiculo_id'))
                ->value('t.capacidad_kg');

            $pesoTotal = (float) $enviosDeParadas->sum('peso_kg');

            if ($pesoTotal > $capacidadKg) {
                $v->errors()->add('paradas', sprintf(
                    'El peso total de los envíos (%s kg) supera la capacidad del vehículo (%s kg).',
                    number_format($pesoTotal, 2, '.', ''),
                    number_format($capacidadKg, 2, '.', ''),
                ));
            }

            // Un despacho solo levanta envíos registrados en su sucursal de origen.
            $guiasOtraSucursal = $enviosDeParadas
                ->where('sucursal_origen_id', '!=', (int) $this->input('sucursal_origen_id'))
                ->pluck('codigo_guia')
                ->implode(', ');

            if ($guiasOtraSucursal !== '') {
                $v->errors()->add('paradas', "Estos envíos pertenecen a otra sucursal de origen: {$guiasOtraSucursal}.");
            }

            $fechados = Despacho::query()
                ->where('fecha', $this->input('fecha'))
                ->whereIn('estado_despacho_id', function ($q): void {
                    $q->select('id_estado')->from('rutas.estados_despachos')->whereIn('nombre_estado', ['PLANIFICADO', 'EN_CURSO']);
                })
                ->where(function ($q): void {
                    $q->where('conductor_empleado_id', $this->input('conductor_empleado_id'))
                        ->orWhere('vehiculo_id', $this->input('vehiculo_id'));
                })
                ->exists();

            if ($fechados) {
                $v->errors()->add('fecha', 'El conductor o el vehículo ya tienen un despacho programado para esa fecha.');
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'sucursal_origen_id.required' => 'Selecciona la sucursal de origen.',
            'conductor_empleado_id.required' => 'Asigna un conductor.',
            'vehiculo_id.required' => 'Asigna un vehículo.',
            'fecha.required' => 'Indica la fecha del despacho.',
            'paradas.required' => 'Agrega al menos una parada al despacho.',
            'paradas.min' => 'Agrega al menos una parada al despacho.',
            'paradas.max' => 'Un despacho no puede superar :max paradas.',
            'paradas.*.envio_id.distinct' => 'Hay envíos repetidos en las paradas.',
            'paradas.*.orden.distinct' => 'Hay paradas con el mismo orden.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'sucursal_origen_id' => 'sucursal de origen',
            'conductor_empleado_id' => 'conductor',
            'vehiculo_id' => 'vehículo',
            'fecha' => 'fecha del despacho',
            'km_planificados' => 'kilómetros planificados',
            'costo_estimado' => 'costo estimado',
        ];
    }
}
