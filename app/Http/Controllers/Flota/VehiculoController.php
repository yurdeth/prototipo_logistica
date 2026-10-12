<?php

namespace App\Http\Controllers\Flota;

use App\Http\Controllers\Controller;
use App\Http\Requests\Flota\StoreVehiculoRequest;
use App\Http\Requests\Flota\UpdateVehiculoRequest;
use App\Models\EstadoVehiculo;
use App\Models\MarcaVehiculo;
use App\Models\ModeloVehiculo;
use App\Models\Sucursal;
use App\Models\TipoVehiculo;
use App\Models\Vehiculo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class VehiculoController extends Controller
{
    /**
     * Listado del parque de vehículos con su tipo, estado y sucursal base.
     */
    public function index(): Response
    {
        return Inertia::render('flota/VehiculosIndex', [
            'vehiculos' => Vehiculo::query()
                ->with(['tipo', 'estado', 'sucursal'])
                ->orderBy('placa')
                ->get(),
        ]);
    }

    /**
     * Formulario de registro con los catálogos: tipos, estados, sucursales,
     * marcas activas y sus modelos.
     */
    public function create(): Response
    {
        return Inertia::render('flota/VehiculosCreate', $this->catalogos());
    }

    /**
     * Registra un vehículo nuevo; la placa no puede repetirse en la flota.
     * La marca y el modelo provienen del catálogo y quedan en la pivote
     * flota.vehiculo_marca_modelo (además de las columnas de lectura).
     */
    public function store(StoreVehiculoRequest $request): RedirectResponse
    {
        $datos = $request->validated();

        [$marca, $modelo] = $this->resolverMarcaModelo($datos);

        $vehiculo = Vehiculo::create([
            ...$datos,
            'marca' => $marca->nombre,
            'modelo' => $modelo?->nombre,
        ]);

        if ($modelo !== null) {
            $vehiculo->modelos()->attach($modelo->id_modelo, [
                'marca_vehiculo_id' => $marca->id_marca,
            ]);
        }

        return redirect()
            ->route('vehiculos.index')
            ->with('success', "Vehículo {$vehiculo->placa} registrado correctamente.");
    }

    /**
     * Formulario de edición con el vehículo hidratado desde la pivote
     * marca-modelo y los catálogos vigentes.
     */
    public function edit(Vehiculo $vehiculo): Response
    {
        return Inertia::render('flota/VehiculosEdit', [
            ...$this->catalogos(),
            'vehiculo' => $vehiculo->load(['tipo', 'estado', 'sucursal', 'modelos']),
        ]);
    }

    /**
     * Actualiza un vehículo existente; la placa no puede chocar con otro.
     */
    public function update(UpdateVehiculoRequest $request, Vehiculo $vehiculo): RedirectResponse
    {
        $datos = $request->validated();

        [$marca, $modelo] = $this->resolverMarcaModelo($datos);

        $vehiculo->update([
            ...$datos,
            'marca' => $marca->nombre,
            'modelo' => $modelo?->nombre,
        ]);

        // La pivote refleja la marca-modelo vigente del vehículo
        $vehiculo->modelos()->sync($modelo === null ? [] : [
            $modelo->id_modelo => ['marca_vehiculo_id' => $marca->id_marca],
        ]);

        return redirect()
            ->route('vehiculos.index')
            ->with('success', "Vehículo {$vehiculo->placa} actualizado correctamente.");
    }

    /**
     * Desactiva el vehículo asignándole el estado INACTIVO: no se elimina,
     * para preservar los registros históricos (despachos, paradas, eventos).
     */
    public function desactivar(Vehiculo $vehiculo): RedirectResponse
    {
        $inactivo = EstadoVehiculo::query()->where('nombre_estado', 'INACTIVO')->firstOrFail();

        $vehiculo->update(['estado_vehiculo_id' => $inactivo->id_estado]);

        return redirect()
            ->route('vehiculos.edit', ['vehiculo' => $vehiculo->id_vehiculo])
            ->with('success', "Vehículo {$vehiculo->placa} desactivado. Conserva su historial operativo.");
    }

    /**
     * Reactiva un vehículo inactivo devolviéndolo al estado DISPONIBLE.
     */
    public function reactivar(Vehiculo $vehiculo): RedirectResponse
    {
        $disponible = EstadoVehiculo::query()->where('nombre_estado', 'DISPONIBLE')->firstOrFail();

        $vehiculo->update(['estado_vehiculo_id' => $disponible->id_estado]);

        return redirect()
            ->route('vehiculos.edit', ['vehiculo' => $vehiculo->id_vehiculo])
            ->with('success', "Vehículo {$vehiculo->placa} reactivado y disponible para despachos.");
    }

    /**
     * Catálogos compartidos por los formularios de registro y edición.
     *
     * @return array<string, mixed>
     */
    private function catalogos(): array
    {
        return [
            'tipos' => TipoVehiculo::orderBy('nombre')->get(),
            'estados' => EstadoVehiculo::orderBy('nombre_estado')->get(),
            'sucursales' => Sucursal::orderBy('nombre_sucursal')->get(),
            'marcas' => MarcaVehiculo::where('activa', true)->orderBy('nombre')->get(),
            'modelos' => ModeloVehiculo::where('activa', true)->orderBy('nombre')->get(),
        ];
    }

    /**
     * Resuelve marca y modelo del catálogo; la validación garantiza la
     * existencia, pero si el catálogo cambió entre carga y envío el error
     * se muestra en el formulario como cualquier validación.
     *
     * @param  array<string, mixed>  $datos
     * @return array{MarcaVehiculo, ModeloVehiculo|null}
     */
    private function resolverMarcaModelo(array $datos): array
    {
        $marca = MarcaVehiculo::query()->whereKey($datos['marca_vehiculo_id'])->first();
        $modelo = $datos['modelo_vehiculo_id'] !== null
            ? ModeloVehiculo::query()->whereKey($datos['modelo_vehiculo_id'])->first()
            : null;

        if ($marca === null || ($datos['modelo_vehiculo_id'] !== null && $modelo === null)) {
            throw ValidationException::withMessages([
                'marca_vehiculo_id' => 'La marca o el modelo indicados no existen en el catálogo.',
            ]);
        }

        return [$marca, $modelo];
    }
}
