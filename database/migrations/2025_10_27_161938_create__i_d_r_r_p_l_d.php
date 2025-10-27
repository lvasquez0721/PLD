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
        Schema::create('IDRRPLD', function (Blueprint $table) {
            $table->id();
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
            $table->decimal('Monto', 18, 6)->nullable();
            $table->string('Moneda')->nullable();
            $table->timestamp('FechaOperacion')->nullable();
            $table->timestamp('FechaDeteccion')->nullable();
            $table->string('Nacionalidad')->nullable();
            $table->string('TipoPersona')->nullable();
            $table->string('RazonSocial')->nullable();
            $table->string('Nombre')->nullable();
            $table->string('APaterno')->nullable();
            $table->string('AMaterno')->nullable();
            $table->string('RFC')->nullable();
            $table->string('CURP')->nullable();
            $table->timestamp('FechaNacimiento')->nullable();
            $table->string('Domicilio')->nullable();
            $table->string('Colonia')->nullable();
            $table->string('Ciudad')->nullable();
            $table->string('Telefono')->nullable();
            $table->string('Ocupacion')->nullable();
            $table->string('NombreAgente')->nullable();
            $table->string('APaternoAgente')->nullable();
            $table->string('AMaternoAgente')->nullable();
            $table->string('RFCAgente')->nullable();
            $table->string('CURPAgente')->nullable();
            $table->string('Cuenta')->nullable();
            $table->string('NoPolizaCuenta')->nullable();
            $table->string('CveSujetoObl')->nullable();
            $table->string('NombreTitular')->nullable();
            $table->string('APaternoTitular')->nullable();
            $table->string('AMaternoTitular')->nullable();
            $table->string('Descripcion')->nullable();
            $table->string('Razon')->nullable();
            $table->string('Estatus')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('IDRRPLD');
    }
};
