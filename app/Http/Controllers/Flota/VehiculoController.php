<?php

namespace App\Http\Controllers\Flota;

use App\Http\Controllers\Controller;
use App\Http\Requests\Flota\StoreVehiculoRequest;
use App\Models\EstadoVehiculo;
use App\Models\MarcaVehiculo;
use App\Models\ModeloVehiculo;
use App\Models\Sucursal;
use App\Models\TipoVehiculo;
use App\Models\Vehiculo;
use Illuminate\Http\JsonResponse;

class VehiculoController extends Controller
{
    /**
     * Listado del parque de vehículos con su tipo, estado y sucursal base.
     */
    public function index(): JsonResponse
    {
        $vehiculos = Vehiculo::query()
            ->with(['tipo', 'estado', 'sucursal'])
            ->orderBy('placa')
            ->get();

        return response()->json(['vehiculos' => $vehiculos]);
    }

    /**
     * Catálogos para el formulario de registro: tipos, estados, sucursales,
     * marcas fijas y sus modelos.
     */
    public function opciones(): JsonResponse
    {
        return response()->json([
            'tipos' => TipoVehiculo::orderBy('nombre')->get(),
            'estados' => EstadoVehiculo::orderBy('nombre_estado')->get(),
            'sucursales' => Sucursal::orderBy('nombre_sucursal')->get(),
            'marcas' => MarcaVehiculo::where('activa', true)->orderBy('nombre')->get(),
            'modelos' => ModeloVehiculo::where('activa', true)->orderBy('nombre')->get(),
        ]);
    }

    /**
     * Registra un vehículo nuevo; la placa no puede repetirse en la flota.
     * La marca y el modelo provienen del catálogo y quedan en la pivote
     * flota.vehiculo_marca_modelo (además de las columnas de lectura).
     */
    public function store(StoreVehiculoRequest $request): JsonResponse
    {
        $datos = $request->validated();

        // La validación ya garantiza la existencia; el guard cubre condiciones de carrera.
        $marca = MarcaVehiculo::query()->whereKey($datos['marca_vehiculo_id'])->first();
        $modelo = $datos['modelo_vehiculo_id'] !== null
            ? ModeloVehiculo::query()->whereKey($datos['modelo_vehiculo_id'])->first()
            : null;

        if ($marca === null || ($datos['modelo_vehiculo_id'] !== null && $modelo === null)) {
            return response()->json([
                'message' => 'La marca o el modelo indicados no existen en el catálogo.',
            ], 422);
        }

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

        return response()->json([
            'message' => "Vehículo {$vehiculo->placa} registrado correctamente.",
            'vehiculo' => $vehiculo->load(['tipo', 'estado', 'sucursal', 'modelos.marca']),
        ], 201);
    }
}
