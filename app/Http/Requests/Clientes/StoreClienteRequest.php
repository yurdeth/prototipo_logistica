<?php

namespace App\Http\Requests\Clientes;

use App\Concerns\Clientes\ReglasCliente;
use Illuminate\Foundation\Http\FormRequest;

class StoreClienteRequest extends FormRequest
{
    use ReglasCliente;

    // Verifica que el usuario tenga permiso para registrar clientes.
    public function authorize(): bool
    {
        return $this->user()?->can('clientes.registrar') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return $this->reglasComunes();
    }
}
