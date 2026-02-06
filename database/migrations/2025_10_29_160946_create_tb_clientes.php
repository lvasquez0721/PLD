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
        // catEstados
        Schema::create('catEstados', function (Blueprint $table) {
            $table->unsignedBigInteger('IDEstado')->primary();
            $table->string('Estado');
            $table->string('IndicePaz')->nullable();
            $table->timestamps();
        });

        // catMunicipio
        // Schema::create('catMunicipio', function (Blueprint $table) {
        //     $table->unsignedBigInteger('IDMunicipio')->primary();
        //     $table->string('Municipio');
        //     $table->unsignedBigInteger('IDEstado');
        //     $table->timestamps();

        //     $table->foreign('IDEstado')->references('IDEstado')->on('catEstados')->onDelete('cascade');
        // });

        // catLocalidad
        // Schema::create('catLocalidad', function (Blueprint $table) {
        //     $table->unsignedBigInteger('IDLocalidad')->primary();
        //     $table->string('Localidad');
        //     $table->unsignedBigInteger('IDMunicipio');
        //     $table->timestamps();

        //     $table->foreign('IDMunicipio')->references('IDMunicipio')->on('catMunicipio')->onDelete('cascade');
        // });

        // catTipoPersona
        Schema::create('catTipoPersona', function (Blueprint $table) {
            $table->unsignedBigInteger('IDTipoPersona')->primary();
            $table->string('TipoPersona');
            $table->timestamps();
        });

        // tbClientes
        Schema::create('tbClientes', function (Blueprint $table) {
            $table->bigIncrements('IDCliente');
            $table->string('RFC', 13);
            $table->string('Nombre');
            $table->string('ApellidoPaterno');
            $table->string('ApellidoMaterno');
            $table->string('RazonSocial')->nullable();
            $table->unsignedBigInteger('IDTipoPersona');
            $table->string('CURP', 18)->nullable();
            $table->unsignedBigInteger('IDOcupacionGiro');
            $table->date('FechaNacimiento')->nullable();
            $table->date('FechaConstitucion')->nullable();
            $table->string('FolioMercantil')->nullable();
            $table->boolean('CoincideEnListasNegras')->default(false);
            $table->boolean('EsPPEActivo')->default(false);
            $table->unsignedBigInteger('IDNacionalidad');
            $table->unsignedBigInteger('IDEstadoNacimiento')->nullable();
            $table->boolean('Activo')->default(true);

            $table->timestamps();
        });

        // tbClientesDomicilio
        Schema::create('tbClientesDomicilio', function (Blueprint $table) {
            $table->bigIncrements('IDDomicilio'); // IDDomicilio es autoincremental
            $table->unsignedBigInteger('IDCliente');
            $table->string('Calle')->nullable();
            $table->string('NoExterior')->nullable();
            $table->string('NoInterior')->nullable();
            $table->string('Colonia')->nullable();
            $table->string('CP')->nullable();
            $table->unsignedBigInteger('IDEstado');
            // $table->unsignedBigInteger('IDMunicipio');
            // $table->unsignedBigInteger('IDLocalidad');
            $table->string('Municipio')->nullable();
            $table->string('Localidad')->nullable();
            $table->string('Telefono')->nullable();
            $table->timestamps();

            $table->foreign('IDCliente')->references('IDCliente')->on('tbClientes')->onDelete('cascade');
            $table->foreign('IDEstado')->references('IDEstado')->on('catEstados')->onDelete('cascade');
            // $table->foreign('IDMunicipio')->references('IDMunicipio')->on('catMunicipio')->onDelete('cascade');
            // $table->foreign('IDLocalidad')->references('IDLocalidad')->on('catLocalidad')->onDelete('cascade');
        });

        // logClientesDomicilio
        Schema::create('logClientesDomicilio', function (Blueprint $table) {
            $table->unsignedBigInteger('IDLogDomicilio')->primary();
            $table->unsignedBigInteger('IDDomicilio')->nullable();
            $table->unsignedBigInteger('IDCliente')->nullable();
            $table->string('Calle')->nullable();
            $table->string('NoExterior')->nullable();
            $table->string('NoInterior')->nullable();
            $table->string('Colonia')->nullable();
            $table->string('CP')->nullable();

            $table->unsignedBigInteger('IDEstado')->nullable();
            // $table->unsignedBigInteger('IDMunicipio')->nullable();
            // $table->unsignedBigInteger('IDLocalidad')->nullable();
            $table->string('Municipio')->nullable();
            $table->string('Localidad')->nullable();
            $table->string('Telefono')->nullable();

            $table->timestamps();

            $table->foreign('IDDomicilio')->references('IDDomicilio')->on('tbClientesDomicilio')->onDelete('set null');
            $table->foreign('IDCliente')->references('IDCliente')->on('tbClientes')->onDelete('set null');
            $table->foreign('IDEstado')->references('IDEstado')->on('catEstados')->onDelete('set null');
            // $table->foreign('IDMunicipio')->references('IDMunicipio')->on('catMunicipio')->onDelete('set null');
            // $table->foreign('IDLocalidad')->references('IDLocalidad')->on('catLocalidad')->onDelete('set null');
        });

        // catSistemas
        Schema::create('catSistemas', function (Blueprint $table) {
            $table->unsignedBigInteger('IDSistema')->primary();
            $table->string('Sistema');
            $table->boolean('Activo')->default(true);
            $table->timestamps();
        });

        // catOcupacionesGiros
        Schema::create('catOcupacionesGiros', function (Blueprint $table) {
            $table->bigIncrements('IDOcupacionGiro');
            $table->string('CVE_GIRO')->nullable();
            $table->string('OcupacionGiro');
        });

        // catNacionalidad
        Schema::create('catNacionalidad', function (Blueprint $table) {
            $table->unsignedBigInteger('IDNacionalidad')->primary();
            $table->string('Nacionalidad');
            $table->timestamps();
        });

        // catIDClientesSistema
        Schema::create('catIDClientesSistema', function (Blueprint $table) {
            $table->unsignedBigInteger('IDOrigenSistema')->primary();
            $table->unsignedBigInteger('IDCliente');
            $table->unsignedBigInteger('IDSistema');
            $table->string('NCliente');
            $table->timestamps();

            $table->foreign('IDCliente')->references('IDCliente')->on('tbClientes')->onDelete('cascade');
            $table->foreign('IDSistema')->references('IDSistema')->on('catSistemas')->onDelete('cascade');
        });

        // tbClientesPPE
        Schema::create('tbClientesPPE', function (Blueprint $table) {
            $table->bigIncrements('IDDeteccionPPE');
            $table->unsignedBigInteger('IDCliente');
            $table->string('Lista')->nullable();
            $table->string('Cargo')->nullable();
            $table->string('Estado')->nullable();
            $table->date('FechaDeteccion')->nullable();
            $table->string('Origen')->nullable();
            $table->timestamp('TimeStampRegistro')->nullable();

            $table->timestamps();

            $table->foreign('IDCliente')->references('IDCliente')->on('tbClientes')->onDelete('cascade');
        });

        // logDetectClientesListas
        Schema::create('logDetectClientesListas', function (Blueprint $table) {
            $table->bigIncrements('IDDeteccion');
            $table->unsignedBigInteger('IDCliente');
            $table->string('Lista')->nullable();
            $table->string('NombreDetectado')->nullable();
            $table->string('Origen')->nullable();
            $table->timestamp('TimeStampDeteccion')->nullable();

            $table->timestamps();

            $table->foreign('IDCliente')->references('IDCliente')->on('tbClientes')->onDelete('cascade');
        });

        // logClientes
        Schema::create('logClientes', function (Blueprint $table) {
            $table->bigIncrements('IDLogCliente');
            $table->unsignedBigInteger('IDCliente')->nullable();
            $table->string('RfcAnterior')->nullable();
            $table->string('Nombre')->nullable();
            $table->string('ApellidoPaterno')->nullable();
            $table->string('ApellidoMaterno')->nullable();
            $table->string('RazonSocial')->nullable();
            $table->unsignedBigInteger('IDTipoPersona')->nullable();
            $table->string('CURP')->nullable();
            $table->unsignedBigInteger('IDOcupacionGiro')->nullable();
            $table->date('FechaNacimiento')->nullable();
            $table->date('FechaConstitucion')->nullable();
            $table->string('FolioMercantil')->nullable();
            $table->boolean('CoincideEnListasNegras')->default(false)->nullable();
            $table->boolean('EsPPEActivo')->default(false)->nullable();
            $table->unsignedBigInteger('IDNacionalidad')->nullable();
            $table->unsignedBigInteger('IDEstadoNacimiento')->nullable();
            $table->boolean('Activo')->default(true)->nullable();
            $table->timestamp('TimeStampLog')->nullable();

            $table->timestamps();

            $table->foreign('IDCliente')->references('IDCliente')->on('tbClientes')->onDelete('set null');
            $table->foreign('IDTipoPersona')->references('IDTipoPersona')->on('catTipoPersona')->onDelete('set null');
            $table->foreign('IDNacionalidad')->references('IDNacionalidad')->on('catNacionalidad')->onDelete('set null');
            $table->foreign('IDEstadoNacimiento')->references('IDEstado')->on('catEstados')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('logClientes');
        Schema::dropIfExists('logClientesDomicilio');
        Schema::dropIfExists('tbClientesDomicilio');
        Schema::dropIfExists('catLocalidad');
        Schema::dropIfExists('catMunicipio');
        Schema::dropIfExists('catEstados');
        Schema::dropIfExists('logDetectClientesListas');
        Schema::dropIfExists('tbClientesPPE');
        Schema::dropIfExists('catIDClientesSistema');
        Schema::dropIfExists('tbClientes');
        Schema::dropIfExists('catTipoPersona');
        Schema::dropIfExists('catNacionalidad');
        Schema::dropIfExists('catOcupacionesGiros');
        Schema::dropIfExists('catSistemas');
    }
};
