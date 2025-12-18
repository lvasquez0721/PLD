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
        Schema::table('tbOperaciones', function (Blueprint $table) {
            //
            $table->unsignedBigInteger('IDMoneda')->after('IDCliente');
            $table->date('FechaInicioVigencia')->after('IDMoneda');
            $table->date('FechaFinVigencia')->after('FechaInicioVigencia');
            $table->string('RazonSocialAgente')->after('FechaFinVigencia');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tbOperaciones', function (Blueprint $table) {
            //
        });
    }
};
