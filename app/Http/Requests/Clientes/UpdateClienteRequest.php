<?php

namespace App\Http\Requests\Clientes;

use App\Concerns\Clientes\ReglasCliente;
use Illuminate\Foundation\Http\FormRequest;

class UpdateClienteRequest extends FormRequest
{
    use ReglasCliente;

    // Verifica que el usuario tenga permiso para editar clientes.
    public function authorize(): bool
    {
        return $this->user()?->can('clientes.editar') ?? false;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return $this->reglasComunes((int) $this->route('cliente'));
    }
}
