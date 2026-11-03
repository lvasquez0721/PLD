<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $path = database_path('seeders/data');

        foreach ($this->json("{$path}/ebr_estados.json") as $row) {
            DB::table('ebr_cat_estados')->updateOrInsert(
                ['estado' => $row['estado']],
                $row + ['activo' => true, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        foreach ($this->json("{$path}/ebr_productos.json") as $row) {
            DB::table('ebr_cat_productos')->updateOrInsert(
                ['producto' => $row['producto']],
                $row + ['activo' => true, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        foreach ($this->json("{$path}/ebr_actividades.json") as $row) {
            DB::table('ebr_cat_actividades')->updateOrInsert(
                ['ocupacion_giro' => $row['ocupacion_giro']],
                $row + ['activo' => true, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        foreach ($this->json("{$path}/ebr_paises.json") as $row) {
            DB::table('ebr_cat_paises')->updateOrInsert(
                ['pais' => $row['pais']],
                $row + ['activo' => true, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }

    public function down(): void
    {
        // Los catálogos son datos maestros de la metodología; no se eliminan al revertir.
    }

    private function json(string $path): array
    {
        if (! is_file($path)) {
            return [];
        }

        return json_decode((string) file_get_contents($path), true) ?: [];
    }
};
