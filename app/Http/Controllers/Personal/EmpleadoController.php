<?php

namespace App\Http\Controllers\Personal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Personal\StoreEmpleadoRequest;
use App\Http\Requests\Personal\UpdateEmpleadoRequest;
use App\Models\Empleado;
use App\Models\Puesto;
use App\Models\Sucursal;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EmpleadoController extends Controller
{
    /**
     * Listado del personal con su puesto, departamento y sucursal.
     */
    public function index(): Response
    {
        return Inertia::render('personal/EmpleadosIndex', [
            'empleados' => Empleado::query()
                ->with(['puesto.departamento', 'sucursal'])
                ->orderBy('apellidos')
                ->orderBy('nombres')
                ->get(),
        ]);
    }

    /**
     * Formulario de registro con los catálogos: puestos (con departamento)
     * y sucursales.
     */
    public function create(): Response
    {
        return Inertia::render('personal/EmpleadosCreate', $this->catalogos());
    }

    /**
     * Registra un empleado nuevo; el documento de identidad es único.
     */
    public function store(StoreEmpleadoRequest $request): RedirectResponse
    {
        // departamento_id no es columna de personal.empleados
        $empleado = Empleado::create($request->safe()->except('departamento_id'));

        return redirect()
            ->route('empleados.index')
            ->with('success', "Empleado {$empleado->nombres} {$empleado->apellidos} registrado correctamente.");
    }

    /**
     * Formulario de edición con el empleado y los catálogos vigentes.
     */
    public function edit(Empleado $empleado): Response
    {
        return Inertia::render('personal/EmpleadosEdit', [
            ...$this->catalogos(),
            'empleado' => $empleado->load(['puesto.departamento', 'sucursal']),
        ]);
    }

    /**
     * Actualiza un empleado existente; documento y usuario únicos ignorando
     * al propio empleado.
     */
    public function update(UpdateEmpleadoRequest $request, Empleado $empleado): RedirectResponse
    {
        $empleado->update($request->safe()->except('departamento_id'));

        return redirect()
            ->route('empleados.index')
            ->with('success', "Empleado {$empleado->nombres} {$empleado->apellidos} actualizado correctamente.");
    }

    /**
     * Desactiva al empleado (activo=false): no se elimina, para conservar su
     * historial de despachos y registros operativos.
     */
    public function desactivar(Empleado $empleado): RedirectResponse
    {
        $empleado->update(['activo' => false]);

        return redirect()
            ->route('empleados.edit', ['empleado' => $empleado->id_empleado])
            ->with('success', "Empleado {$empleado->nombres} {$empleado->apellidos} desactivado. Conserva su historial.");
    }

    /**
     * Reactiva un empleado inactivo (activo=true).
     */
    public function reactivar(Empleado $empleado): RedirectResponse
    {
        $empleado->update(['activo' => true]);

        return redirect()
            ->route('empleados.edit', ['empleado' => $empleado->id_empleado])
            ->with('success', "Empleado {$empleado->nombres} {$empleado->apellidos} reactivado.");
    }

    /**
     * Catálogos compartidos por los formularios de registro y edición.
     *
     * @return array<string, mixed>
     */
    private function catalogos(): array
    {
        return [
            'puestos' => Puesto::query()
                ->with('departamento')
                ->orderBy('nombre_puesto')
                ->get(),
            'sucursales' => Sucursal::orderBy('nombre_sucursal')->get(),
        ];
    }
}
