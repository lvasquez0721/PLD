<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Primero elimina la restricción de clave foránea (por convención Laravel la nombra tboperacionespagos_idformapago_foreign)
        Schema::table('tbOperacionesPagos', function (Blueprint $table) {
            // Verifica y elimina la foreign si existe antes de eliminar la columna
            try {
                DB::statement('ALTER TABLE `tbOperacionesPagos` DROP FOREIGN KEY `tboperacionespagos_idformapago_foreign`');
            } catch (\Exception $e) {
                // Silenciar si la foreign key no existe
            }
        });

        // Ahora elimina la columna, si existe
        Schema::table('tbOperacionesPagos', function (Blueprint $table) {
            if (Schema::hasColumn('tbOperacionesPagos', 'IDFormaPago')) {
                $table->dropColumn('IDFormaPago');
            }
        });

        // Crea nuevamente la columna como string (varchar), nullable y DESPUÉS de "TipoCambio"
        Schema::table('tbOperacionesPagos', function (Blueprint $table) {
            $table->string('IDFormaPago')->nullable()->after('TipoCambio');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Elimina la columna IDFormaPago como string
        Schema::table('tbOperacionesPagos', function (Blueprint $table) {
            if (Schema::hasColumn('tbOperacionesPagos', 'IDFormaPago')) {
                $table->dropColumn('IDFormaPago');
            }
        });

        // Opcionalmente, restaura la columna como era originalmente
        /*
        Schema::table('tbOperacionesPagos', function (Blueprint $table) {
            $table->integer('IDFormaPago')->nullable()->after('TipoCambio');

            // Si necesitas también la restricción de clave foránea (ajusta la tabla de referencia):
            // $table->foreign('IDFormaPago')->references('ID')->on('tabla_padre')->onDelete('set null');
        });
        */
    }
};
