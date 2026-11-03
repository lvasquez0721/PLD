<?php

namespace Database\Seeders;

use App\Models\Ebr\EbrCatActividad;
use App\Models\Ebr\EbrCatEstado;
use App\Models\Ebr\EbrCatPais;
use App\Models\Ebr\EbrCatProducto;
use Illuminate\Database\Seeder;

class EbrCatalogoSeeder extends Seeder
{
    public function run(): void
    {
        $dataPath = database_path('seeders/data');

        foreach ($this->json("{$dataPath}/ebr_estados.json") as $estado) {
            EbrCatEstado::updateOrCreate(
                ['estado' => $estado['estado']],
                $estado + ['activo' => true]
            );
        }

        foreach ($this->json("{$dataPath}/ebr_productos.json") as $producto) {
            EbrCatProducto::updateOrCreate(
                ['producto' => $producto['producto']],
                $producto + ['activo' => true]
            );
        }

        foreach ($this->json("{$dataPath}/ebr_actividades.json") as $actividad) {
            EbrCatActividad::updateOrCreate(
                ['ocupacion_giro' => $actividad['ocupacion_giro']],
                $actividad + ['activo' => true]
            );
        }

        foreach ($this->json("{$dataPath}/ebr_paises.json") as $pais) {
            EbrCatPais::updateOrCreate(
                ['pais' => $pais['pais']],
                $pais + ['activo' => true]
            );
        }
    }

    private function json(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        return json_decode((string) file_get_contents($path), true) ?: [];
    }
}
