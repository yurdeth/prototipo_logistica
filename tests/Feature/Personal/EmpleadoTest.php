<?php

namespace Tests\Feature\Personal;

use App\Models\Departamento;
use App\Models\Empleado;
use App\Models\Puesto;
use App\Models\Sucursal;
use App\Models\Territorio;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmpleadoTest extends TestCase
{
    use RefreshDatabase;

    private User $usuario;

    private array $datosBase;

    protected function setUp(): void
    {
        parent::setUp();

        $territorio = Territorio::create([
            'nombre_territorio' => 'Santa Cruz',
            'tipo_territorio' => 'DEPARTAMENTO',
        ]);
        $sucursal = Sucursal::create([
            'codigo' => 'SCZ-01',
            'nombre_sucursal' => 'Bodega Base',
            'territorio_id' => $territorio->id_territorio,
            'direccion' => 'Base operativa',
        ]);

        $operaciones = Departamento::create(['nombre_departamento' => 'Operaciones']);
        $bodega = Departamento::create(['nombre_departamento' => 'Bodega']);
        $conductor = Puesto::create(['departamento_id' => $operaciones->id_departamento, 'nombre_puesto' => 'Conductor']);
        Puesto::create(['departamento_id' => $bodega->id_departamento, 'nombre_puesto' => 'Bodeguero']);

        $this->usuario = User::factory()->create();
        $this->datosBase = [
            'nombres' => 'Juan',
            'apellidos' => 'Perez',
            'documento_identidad' => '1234567',
            'puesto_id' => $conductor->id_puesto,
            'sucursal_id' => $sucursal->id_sucursal,
            'departamento_id' => $operaciones->id_departamento,
            'telefono' => '70001234',
            'numero_licencia' => 'LIC-001',
            'fecha_contratacion' => '2025-01-15',
            'activo' => true,
        ];
    }

    public function test_registra_un_empleado_valido(): void
    {
        $this->actingAs($this->usuario)
            ->post('/empleados', $this->datosBase)
            ->assertRedirect('/empleados');

        expect(Empleado::where('documento_identidad', '1234567')->exists())->toBeTrue();
    }

    public function test_los_formularios_cargan_puestos_y_sucursales_desde_la_base(): void
    {
        // Los catálogos deben llegar sembrados en la base, no escritos a mano en la vista
        $this->actingAs($this->usuario)
            ->get('/empleados/crear')
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('personal/EmpleadosCreate')
                    ->where('puestos', fn ($puestos) => $puestos->pluck('nombre_puesto')->contains('Conductor'))
                    ->where('sucursales', fn ($sucursales) => $sucursales->pluck('nombre_sucursal')->contains('Bodega Base')),
            );
    }

    public function test_rechaza_documento_duplicado(): void
    {
        Empleado::create($this->datosBase);

        $this->actingAs($this->usuario)
            ->post('/empleados', $this->datosBase)
            ->assertSessionHasErrors('documento_identidad');

        expect(Empleado::count())->toBe(1);
    }

    public function test_rechaza_licencia_duplicada(): void
    {
        Empleado::create($this->datosBase);

        $this->actingAs($this->usuario)
            ->post('/empleados', [...$this->datosBase, 'documento_identidad' => '7654321'])
            ->assertSessionHasErrors('numero_licencia');

        expect(Empleado::count())->toBe(1);
    }

    /** Varios empleados sin licencia conviven: el unique solo aplica al valor presente. */
    public function test_permite_varios_empleados_sin_licencia(): void
    {
        Empleado::create([...$this->datosBase, 'numero_licencia' => null]);

        $this->actingAs($this->usuario)
            ->post('/empleados', [...$this->datosBase, 'documento_identidad' => '7654321', 'numero_licencia' => null])
            ->assertRedirect('/empleados');

        expect(Empleado::count())->toBe(2);
    }

    public function test_valida_campos_obligatorios_y_existencias(): void
    {
        $this->actingAs($this->usuario)
            ->post('/empleados', [
                'nombres' => '',
                'apellidos' => '',
                'documento_identidad' => '',
                'puesto_id' => 99999,
                'sucursal_id' => 99999,
                'fecha_contratacion' => '2099-01-01',
            ])
            ->assertSessionHasErrors([
                'nombres',
                'apellidos',
                'documento_identidad',
                'puesto_id',
                'sucursal_id',
                'fecha_contratacion',
            ]);

        expect(Empleado::count())->toBe(0);
    }

    public function test_rechaza_puesto_de_otro_departamento(): void
    {
        $bodeguero = Puesto::where('nombre_puesto', 'Bodeguero')->first();
        $operaciones = Departamento::where('nombre_departamento', 'Operaciones')->first();

        $this->actingAs($this->usuario)
            ->post('/empleados', [
                ...$this->datosBase,
                'puesto_id' => $bodeguero->id_puesto,
                'departamento_id' => $operaciones->id_departamento,
            ])
            ->assertSessionHasErrors('puesto_id');

        expect(Empleado::count())->toBe(0);
    }

    public function test_actualiza_sin_chocar_con_su_propio_documento(): void
    {
        $empleado = Empleado::create($this->datosBase);

        $this->actingAs($this->usuario)
            ->put("/empleados/{$empleado->id_empleado}", [...$this->datosBase, 'telefono' => '71112222'])
            ->assertRedirect('/empleados');

        expect($empleado->refresh()->telefono)->toBe('71112222')
            ->and(Empleado::count())->toBe(1);
    }

    public function test_rechaza_editar_con_licencia_de_otro_empleado(): void
    {
        Empleado::create($this->datosBase);

        $otro = Empleado::create([...$this->datosBase, 'documento_identidad' => '7654321', 'numero_licencia' => 'LIC-002']);

        $this->actingAs($this->usuario)
            ->put("/empleados/{$otro->id_empleado}", [...$this->datosBase, 'documento_identidad' => '1111111'])
            ->assertSessionHasErrors('numero_licencia');

        expect($otro->refresh()->numero_licencia)->toBe('LIC-002');
    }

    public function test_pagina_de_edicion_muestra_el_empleado(): void
    {
        $empleado = Empleado::create($this->datosBase);

        $this->actingAs($this->usuario)
            ->get("/empleados/{$empleado->id_empleado}/editar")
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->component('personal/EmpleadosEdit')
                    ->where('empleado.documento_identidad', '1234567'),
            );
    }

    public function test_desactiva_y_reactiva_sin_eliminar(): void
    {
        $empleado = Empleado::create($this->datosBase);

        $this->actingAs($this->usuario)
            ->post("/empleados/{$empleado->id_empleado}/desactivar")
            ->assertRedirect();

        expect($empleado->refresh()->activo)->toBeFalse()
            ->and(Empleado::count())->toBe(1);

        $this->actingAs($this->usuario)
            ->post("/empleados/{$empleado->id_empleado}/reactivar")
            ->assertRedirect();

        expect($empleado->refresh()->activo)->toBeTrue();
    }
}
