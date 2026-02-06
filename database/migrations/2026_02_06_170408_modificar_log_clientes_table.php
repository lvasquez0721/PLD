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
        // Eliminar posibles foreign keys sobre los campos a modificar
        try {
            DB::statement('ALTER TABLE `logClientes` DROP FOREIGN KEY `logClientes_IDNacionalidad_foreign`');
        } catch (\Exception $e) {
        }
        try {
            DB::statement('ALTER TABLE `logClientes` DROP FOREIGN KEY `logClientes_IDEstadoNacimiento_foreign`');
        } catch (\Exception $e) {
        }

        // Cambiar ambos campos a string
        Schema::table('logClientes', function (Blueprint $table) {
            $table->string('IDNacionalidad')->nullable()->change();
            $table->string('IDEstadoNacimiento')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Restaurar a unsignedBigInteger (estado original)
        Schema::table('logClientes', function (Blueprint $table) {
            $table->unsignedBigInteger('IDNacionalidad')->nullable()->change();
            $table->unsignedBigInteger('IDEstadoNacimiento')->nullable()->change();
            // Si se requiere restablecer los foreign keys:
            // $table->foreign('IDNacionalidad')->references('IDNacionalidad')->on('catNacionalidad')->onDelete('set null');
            // $table->foreign('IDEstadoNacimiento')->references('IDEstado')->on('catEstados')->onDelete('set null');
        });
    }
};
