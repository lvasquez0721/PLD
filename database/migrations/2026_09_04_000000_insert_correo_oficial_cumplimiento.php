<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $fechaActual = Carbon::now();

        $existe = DB::table('catCorreoNotificaciones')
            ->where('Archivo', 'OficialCumplimiento')
            ->exists();

        if (! $existe) {
            DB::table('catCorreoNotificaciones')->insert([
                'Archivo' => 'OficialCumplimiento',
                'Correo' => 'oficial.cumplimiento@example.com',
                'Nombre' => 'Oficial de Cumplimiento',
                'Activo' => 1,
                'created_at' => $fechaActual,
                'updated_at' => $fechaActual,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('catCorreoNotificaciones')
            ->where('Archivo', 'OficialCumplimiento')
            ->delete();
    }
};
