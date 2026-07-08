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
        // Crear tabla catálogo de tipos de pago
        Schema::create('catTipoPagos', function (Blueprint $table) {
            $table->bigIncrements('IDTipoPago');
            $table->string('TipoPago', 100)->unique();
            $table->timestamps();
        });

        // Insertar los tipos de pago predeterminados
        DB::table('catTipoPagos')->insert([
            ['IDTipoPago' => 1, 'TipoPago' => 'Mensual',                 'created_at' => now(), 'updated_at' => now()],
            ['IDTipoPago' => 2, 'TipoPago' => 'Pagos parciales',         'created_at' => now(), 'updated_at' => now()],
            ['IDTipoPago' => 3, 'TipoPago' => 'Trimestral',              'created_at' => now(), 'updated_at' => now()],
            ['IDTipoPago' => 4, 'TipoPago' => 'Una sola exhibición',     'created_at' => now(), 'updated_at' => now()],
            ['IDTipoPago' => 5, 'TipoPago' => 'Fraccionado',             'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('catTipoPagos');
    }
};
