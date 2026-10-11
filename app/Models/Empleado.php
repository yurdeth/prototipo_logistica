<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id_empleado
 * @property int|null $usuario_id
 * @property int $puesto_id
 * @property int $sucursal_id
 * @property string $nombres
 * @property string $apellidos
 * @property string $documento_identidad
 * @property string|null $telefono
 * @property string|null $numero_licencia
 * @property string $fecha_contratacion
 * @property bool $activo
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Table('personal.empleados')]
#[Fillable(['usuario_id', 'puesto_id', 'sucursal_id', 'nombres', 'apellidos', 'documento_identidad', 'telefono', 'numero_licencia', 'fecha_contratacion', 'activo'])]
class Empleado extends Model
{
    protected $primaryKey = 'id_empleado';

    // TODO(conductor): vincular con Despacho (un empleado con numero_licencia conduce despachos; despacho.empleado_id) cuando exista el modelo
    /**
     * @return BelongsTo<Puesto, $this>
     */
    public function puesto(): BelongsTo
    {
        return $this->belongsTo(Puesto::class, 'puesto_id', 'id_puesto');
    }

    /**
     * @return BelongsTo<Sucursal, $this>
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id', 'id_sucursal');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id', 'id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'activo' => 'boolean',
            'fecha_contratacion' => 'date',
        ];
    }
}
