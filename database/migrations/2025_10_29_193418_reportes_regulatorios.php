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
        //
        Schema::create('tbReporteRegulatorioPLD', function (Blueprint $table) {
            $table->bigIncrements('IDReporte'); // PK, autoincremental
            $table->unsignedBigInteger('IDRegistroAlerta')->nullable();
            $table->string('TipoReporte')->nullable();
            $table->string('PeriodoReporte')->nullable();
            $table->string('Folio')->nullable();
            $table->string('OrganoSupervisor')->nullable();
            $table->string('CveSujetoObligado')->nullable();
            $table->string('Localidad')->nullable();
            $table->string('Sucursal')->nullable();
            $table->string('TipoOperacion')->nullable();
            $table->string('InstrumentoMonetario')->nullable();
            $table->string('NoPoliza')->nullable();
            $table->decimal('Monto', 18, 2)->nullable();
            $table->unsignedBigInteger('IDMoneda')->nullable(); // Relación con la moneda
            $table->date('FechaOperacion')->nullable();
            $table->date('FechaDeteccion')->nullable();
            $table->string('Nacionalidad')->nullable();
            $table->string('TipoPersona')->nullable();
            $table->string('RazonSocial')->nullable();
            $table->string('Nombre')->nullable();
            $table->string('APaterno')->nullable();
            $table->string('AMaterno')->nullable();
            $table->string('RFC', 13)->nullable();
            $table->string('CURP', 18)->nullable();
            $table->date('FechaNacimiento')->nullable();
            $table->string('Domicilio')->nullable();
            $table->string('Colonia')->nullable();
            $table->string('Ciudad')->nullable();
            $table->string('Telefono')->nullable();
            $table->string('Ocupacion')->nullable();
            $table->string('NombreAgente')->nullable();
            $table->string('APaternoAgente')->nullable();
            $table->string('AMaternoAgente')->nullable();
            $table->string('RFCAgente')->nullable();
            $table->string('CURPAgente', 18)->nullable();
            $table->string('Cuenta')->nullable();
            $table->string('NoPolizaCuenta')->nullable();
            $table->string('CveSujetoObl')->nullable();
            $table->string('NombreTitular')->nullable();
            $table->string('APaternoTitular')->nullable();
            $table->string('AMaternoTitular')->nullable();
            $table->string('Descripcion')->nullable();
            $table->text('Razon')->nullable();
            $table->string('Estatus')->nullable();

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
