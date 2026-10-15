<?php

namespace App\Concerns\Clientes;

use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Reglas compartidas por StoreClienteRequest y UpdateClienteRequest.
 *
 * La existencia se comprueba con closures sobre DB::table('schema.tabla'): la regla
 * `exists:envios.clientes,...` interpretaría "envios" como conexión, no como schema.
 */
trait ReglasCliente
{
    /**
     * @param  int|null  $ignorarId  Cliente que se está editando (no cuenta como duplicado de sí mismo).
     * @return array<string, array<int, mixed>>
     */
    protected function reglasComunes(?int $ignorarId = null): array
    {
        return [
            'nombre' => ['required', 'string', 'min:3', 'max:150', $this->nombreUnico($ignorarId)],
            'tipo_cliente' => ['required', Rule::in(['PERSONA', 'EMPRESA'])],
            'contacto' => ['nullable', 'string', 'max:150'],
            'territorio_id' => ['required', 'integer', $this->territorioExiste()],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'required' => 'El campo :attribute es obligatorio.',
            'integer' => 'El campo :attribute no es válido.',
            'string' => 'El campo :attribute debe ser texto.',
            'min.string' => 'El campo :attribute debe tener al menos :min caracteres.',
            'max.string' => 'El campo :attribute no puede superar :max caracteres.',
            'tipo_cliente.in' => 'Elige si el cliente es una persona o una empresa.',
            'territorio_id.required' => 'Selecciona el territorio del cliente.',
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'nombre' => 'nombre',
            'tipo_cliente' => 'tipo de cliente',
            'contacto' => 'contacto',
            'territorio_id' => 'territorio',
        ];
    }

    /** El nombre identifica al cliente en los envíos: no se permiten duplicados (sin distinguir mayúsculas). */
    protected function nombreUnico(?int $ignorarId): Closure
    {
        return function (string $atributo, mixed $valor, Closure $fail) use ($ignorarId): void {
            $consulta = DB::table('envios.clientes')
                ->whereRaw('lower(nombre) = lower(?)', [trim((string) $valor)]);

            if ($ignorarId !== null) {
                $consulta->where('id_cliente', '<>', $ignorarId);
            }

            if ($consulta->exists()) {
                $fail('Ya existe un cliente con ese nombre.');
            }
        };
    }

    protected function territorioExiste(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $fail): void {
            if (! DB::table('territorio.territorios')->where('id_territorio', $valor)->exists()) {
                $fail('El territorio seleccionado no existe.');
            }
        };
    }
}
