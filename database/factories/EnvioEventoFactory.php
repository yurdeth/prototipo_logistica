<?php

namespace Database\Factories;

use App\Models\Envio;
use App\Models\EnvioEvento;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\DB;

/**
 * @extends Factory<EnvioEvento>
 */
class EnvioEventoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'envio_id' => Envio::factory(),
            'estado_envio_id' => $this->estadoId('REGISTRADO'),
            'ocurrido_en' => now(),
            'nota' => fake()->randomElement(['Envío registrado', 'Recolectado en sucursal', 'En tránsito', 'Entregado al destinatario']),
        ];
    }

    private function estadoId(string $nombre): int
    {
        return (int) DB::table('envios.estados_envios')->where('nombre_estado', $nombre)->value('id_estado');
    }
}
