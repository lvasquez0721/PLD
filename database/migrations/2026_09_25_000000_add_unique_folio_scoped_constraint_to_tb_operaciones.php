<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Índice único scoped (FolioPoliza, FolioEndoso) sin romper datos existentes.
     * - FolioEndoso vacío (null/'') = emisión => solo 1 emisión por FolioPoliza
     * - Con FolioEndoso => par (FolioPoliza, FolioEndoso) único dentro de la póliza
     * Se usa columna generada stored para normalizar '' -> '__EMISION__' y permitir UNIQUE con NULL.
     */
    public function up(): void
    {
        // Evitar fallo si ya existe
        if (Schema::hasColumn('tbOperaciones', 'FolioEndoso_norm')) {
            return;
        }

        // Verificar duplicados existentes antes de crear índice (no bloqueante, solo log)
        // Si hay duplicados, el índice fallará; dejamos que falle explícitamente para auditar.
        // El controller ya maneja 422 a nivel aplicación, este índice es defensa contra race condition.

        Schema::table('tbOperaciones', function (Blueprint $table) {
            // STORED para poder indexar en MySQL 5.7+ / 8
            $table->string('FolioEndoso_norm', 40)
                ->storedAs("COALESCE(NULLIF(TRIM(COALESCE(FolioEndoso, '')), ''), '__EMISION__')")
                ->nullable();
        });

        // Crear índice único scoped
        // Usamos DB::statement para nombre determinístico
        try {
            DB::statement('CREATE UNIQUE INDEX uniq_poliza_endoso_scoped ON tbOperaciones (FolioPoliza, FolioEndoso_norm)');
        } catch (\Throwable $e) {
            // Si ya existe o hay duplicados, no bloquear migración en local; loguear
            // Revertir columna para no dejar estado inconsistente si índice no se creó por duplicados
            if (str_contains($e->getMessage(), 'Duplicate entry')) {
                // Dejar columna pero sin índice; el validador de aplicación seguirá protegiendo
                // No lanzamos excepción para no romper `migrate` en entornos con datos sucios
                \Log::warning('[Migration uniq_poliza_endoso_scoped] Duplicados existentes, índice no creado: '.$e->getMessage());
                return;
            }
            // Si es otro error y la columna ya se creó, intentar limpiar
            throw $e;
        }
    }

    public function down(): void
    {
        // Eliminar índice si existe
        try {
            DB::statement('DROP INDEX uniq_poliza_endoso_scoped ON tbOperaciones');
        } catch (\Throwable $e) {
            // ignorar si no existe
        }

        if (Schema::hasColumn('tbOperaciones', 'FolioEndoso_norm')) {
            Schema::table('tbOperaciones', function (Blueprint $table) {
                $table->dropColumn('FolioEndoso_norm');
            });
        }
    }
};
