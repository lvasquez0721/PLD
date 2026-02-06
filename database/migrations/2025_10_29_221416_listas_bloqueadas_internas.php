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
        // tbListasNegraCNSF
        Schema::create('tbListasNegraCNSF', function (Blueprint $table) {
            $table->bigIncrements('IDRegistroListaCNSF'); // PK autoincremental
            $table->string('Nombre');
            $table->string('Direccion')->nullable();
            $table->string('RFC', 13)->nullable()->comment('Registro Federal de Contribuyentes');
            $table->string('CURP', 18)->nullable()->comment('Clave Única de Registro de Población');
            $table->string('Pais')->nullable()->comment('País');
            $table->date('FechaNacimiento')->nullable()->comment('Fecha de nacimiento');
            $table->text('OficiosRelacionados')->nullable()->comment('Oficios relacionados con el registro');
            $table->string('UsuarioAlta')->nullable()->comment('Usuario que dio de alta el registro');
            $table->timestamp('TimeStampAlta')->nullable()->comment('Fecha y hora de alta del registro');
            $table->string('UsuarioModif')->nullable()->comment('Usuario que modificó el registro');
            $table->timestamp('TimeStampModif')->nullable()->comment('Fecha y hora de modificación del registro');
            $table->timestamps();
        });

        Schema::create('tbListasNegrasUIF', function (Blueprint $table) {
            $table->bigIncrements('IDRegistroListaUIF'); // PK, autoincremental
            $table->string('Nombre');
            $table->string('RFC', 13)->nullable()->comment('Registro Federal de Contribuyentes');
            $table->string('CURP', 18)->nullable()->comment('Clave Única de Registro de Población');
            $table->date('FechaNacimiento')->nullable()->comment('Fecha de nacimiento');
            $table->date('FechaPubAcuerdo')->nullable()->comment('Fecha de publicación del acuerdo');
            $table->string('Acuerdo')->nullable()->comment('Acuerdo relacionado');
            $table->string('NoOficioUIF')->nullable()->comment('Número de oficio UIF');
            $table->integer('AnioLista')->nullable()->comment('Año de la lista');
            $table->string('UsuarioAlta')->nullable()->comment('Usuario que dio de alta el registro');
            $table->timestamp('TimeStampAlta')->nullable()->comment('Fecha y hora de alta del registro');
            $table->string('UsuarioModif')->nullable()->comment('Usuario que modificó el registro');
            $table->timestamp('TimeStampModif')->nullable()->comment('Fecha y hora de modificación del registro');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
