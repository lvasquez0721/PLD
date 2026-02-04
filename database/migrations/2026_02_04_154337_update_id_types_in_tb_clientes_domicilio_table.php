<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Paso 1: Eliminar la restricción de clave foránea antes de modificar el tipo de la columna
        Schema::table('tbClientesDomicilio', function (Blueprint $table) {
            $table->dropForeign(['IDEstado']);
        });

        // Paso 2: Cambiar el tipo de la columna IDEstado a string (varchar 10 y nullable)
        Schema::table('tbClientesDomicilio', function (Blueprint $table) {
            $table->string('IDEstado', 10)->nullable()->change();
        });

        // NOTA: Si quieres volver a poner una foreign key a la nueva columna,
        // deberás asegurarte que la columna referenciada sea también varchar(10),
        // y agregar el constraint aquí. Si no, omitirlo.
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Paso 1: Cambiar la columna IDEstado de vuelta a unsignedBigInteger
        Schema::table('tbClientesDomicilio', function (Blueprint $table) {
            $table->unsignedBigInteger('IDEstado')->nullable()->change();
        });

        // Paso 2: Volver a crear la restricción de clave foránea si aplica
        Schema::table('tbClientesDomicilio', function (Blueprint $table) {
            $table->foreign('IDEstado')->references('IDEstado')->on('tbEstados');
        });
    }
};
