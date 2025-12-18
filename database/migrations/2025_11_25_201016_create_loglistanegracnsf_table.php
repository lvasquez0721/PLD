<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loglistanegracnsf', function (Blueprint $table) {
            $table->bigIncrements('IDLogRegistroListaCNSF'); // AUTO_INCREMENT PRIMARY KEY

            $table->unsignedBigInteger('IDRegistroListaCNSF')->nullable();
            $table->integer('IDAccion')->nullable();
            $table->string('Nombre', 250)->nullable();
            $table->string('Direccion', 250)->nullable();
            $table->string('RFC', 13)->nullable()->comment('Registro Federal de Contribuyentes');
            $table->string('CURP', 18)->nullable()->comment('Clave Única de Registro de Población');
            $table->string('Pais', 255)->nullable()->comment('País');
            $table->date('FechaNacimiento')->nullable()->comment('Fecha de nacimiento');
            $table->text('OficiosRelacionados')->nullable()->comment('Oficios relacionados con el registro');
            $table->string('UsuarioAlta', 255)->nullable()->comment('Usuario que dio de alta el registro');
            $table->timestamp('TimeStampAlta')->nullable()->comment('Fecha y hora de alta del registro');
            $table->string('UsuarioModif', 255)->nullable()->comment('Usuario que modificó el registro');
            $table->timestamp('TimeStampModif')->nullable()->comment('Fecha y hora de modificación del registro');

            // Campos de Laravel ya incluidos en tu dump
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loglistanegracnsf');
    }
};
