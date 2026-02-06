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
        // Eliminar la restricción de clave foránea en IDEstado manualmente antes del cambio de tipo
        // El nombre de la clave puede variar, pero normalmente sigue la convención 'logclientesdomicilio_idestado_foreign'
        // Se recomienda hacerlo via comando SQL para asegurar la eliminación
        try {
            DB::statement('ALTER TABLE `logClientesDomicilio` DROP FOREIGN KEY `logclientesdomicilio_idestado_foreign`');
        } catch (\Exception $e) {
            // Silenciar, la foreign podría ya no existir
        }

        // Ahora cambiamos el tipo de la columna
        Schema::table('logClientesDomicilio', function (Blueprint $table) {
            $table->string('IDEstado')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Volver a hacer IDEstado unsignedBigInteger y nullable
        Schema::table('logClientesDomicilio', function (Blueprint $table) {
            $table->unsignedBigInteger('IDEstado')->nullable()->change();
        });

        // Reagregar la foreign key
        try {
            Schema::table('logClientesDomicilio', function (Blueprint $table) {
                $table->foreign('IDEstado')->references('IDEstado')->on('catEstados')->onDelete('set null');
            });
        } catch (\Exception $e) {
            // Silenciar errores si ya existe
        }
    }
};
