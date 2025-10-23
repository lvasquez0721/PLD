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
        Schema::create('tbPolizas', function (Blueprint $table) {
            // Primary key
            $table->id('IDPoliza');

            // Foreign keys and relationships
            $table->unsignedBigInteger('IDSolicitud')->nullable();
            $table->unsignedBigInteger('IDCiclo')->nullable();
            $table->unsignedBigInteger('IDModalidad')->nullable();
            $table->unsignedBigInteger('IDCultivo')->nullable();
            $table->unsignedBigInteger('IDEstado')->nullable();
            $table->unsignedBigInteger('IDRamo')->nullable();
            $table->unsignedBigInteger('IDSubramo')->nullable();
            $table->unsignedBigInteger('IDProducto')->nullable();
            $table->unsignedBigInteger('IDMetodoEva')->nullable();
            $table->unsignedBigInteger('IDUnidadRiesgo')->nullable();
            $table->unsignedBigInteger('IDMoneda')->nullable();
            $table->unsignedBigInteger('IDOficina')->nullable();
            $table->unsignedBigInteger('IDAgente')->nullable();
            $table->unsignedBigInteger('IDFormaPago')->nullable();
            $table->unsignedBigInteger('IDConvenio')->nullable();
            $table->unsignedBigInteger('IDFunciones')->nullable();
            $table->unsignedBigInteger('IDEspecies')->nullable();

            // Policy information
            $table->string('NoPoliza', 100)->nullable();
            $table->string('NoRRP', 100)->nullable();
            $table->date('FEmisionPoliza')->nullable();
            $table->string('StatusPoliza', 50)->nullable();
            $table->boolean('SuperAsegurada')->default(false);
            $table->decimal('SumaAsegurada', 15, 2)->nullable();
            $table->decimal('SumaASeguradaTotal', 15, 2)->nullable();
            $table->decimal('PrimaTotal', 15, 2)->nullable();

            // Date fields
            $table->date('FIV')->nullable(); // Fecha Inicio Vigencia
            $table->date('FFV')->nullable(); // Fecha Fin Vigencia
            $table->date('FLPPoliza')->nullable(); // Fecha Limite Pago Poliza

            // Premium details
            $table->decimal('PrimaAsegurado', 15, 2)->nullable();
            $table->decimal('PrimaGob', 15, 2)->nullable();
            $table->decimal('PrimaApoyos', 15, 2)->nullable();
            $table->decimal('Tarifa', 10, 4)->nullable();
            $table->decimal('Rendimiento', 10, 4)->nullable();
            $table->decimal('Precio', 15, 2)->nullable();
            $table->decimal('GastoEmision', 15, 2)->nullable();

            // Subsidy and support information
            $table->boolean('SubSidioGobSiNo')->default(false);
            $table->decimal('PorcentajeGob', 5, 2)->nullable();
            $table->boolean('ApoyoCruzadaSiNo')->default(false);
            $table->decimal('PorcentajeApoyos', 5, 2)->nullable();

            // Beneficiaries and payment
            $table->text('Beneficiarios')->nullable();
            $table->date('FechaPago')->nullable();
            $table->string('FormaPago', 100)->nullable();
            $table->decimal('MontoPagado', 15, 2)->nullable();
            $table->string('ConceptoPago', 255)->nullable();
            $table->decimal('IndemnizacionTotal', 15, 2)->nullable();

            // Agent commission
            $table->decimal('ComisionAgente', 10, 2)->nullable();

            // User and year
            $table->string('Usuario', 100)->nullable();
            $table->year('año')->nullable();

            // Additional policy details
            $table->string('FLDFolioPoliza', 100)->nullable();
            $table->boolean('Coaseguro')->default(false);
            $table->boolean('Franquicia')->default(false);
            $table->string('Cve_convenios', 50)->nullable();
            $table->string('Conv_prop', 50)->nullable();
            $table->string('TipoPoliza', 50)->nullable();
            $table->text('ObservacionesCaratula')->nullable();
            $table->boolean('PagoPorlintermediario')->default(false);
            $table->boolean('FacturaPorProductor')->default(false);
            $table->string('FolioSolicitud', 100)->nullable();

            // File information
            $table->string('PathArchivo', 500)->nullable();
            $table->string('NombreArchivo', 255)->nullable();

            // Insurance legend
            $table->text('LeyendaAseguramiento')->nullable();

            // Timestamps
            $table->timestamps();

            // Indexes for better performance
            $table->index('NoPoliza');
            $table->index('FEmisionPoliza');
            $table->index('StatusPoliza');
            $table->index('IDSolicitud');
            $table->index('IDAgente');
            $table->index('año');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
        Schema::dropIfExists('tbPolizas');
    }
};
