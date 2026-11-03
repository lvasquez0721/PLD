<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ebr_ejercicios', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('anio')->unique();
            $table->string('nombre', 120)->nullable();
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->enum('estado', ['borrador', 'calculado', 'cerrado'])->default('borrador');
            $table->string('version_metodologia', 20)->default('2025');
            $table->json('mitigantes')->nullable();
            $table->json('resultados')->nullable();
            $table->json('totales')->nullable();
            $table->json('comparativo')->nullable();
            $table->json('advertencias')->nullable();
            $table->text('observaciones')->nullable();
            $table->foreignId('importado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('calculado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('calculado_at')->nullable();
            $table->timestamp('cerrado_at')->nullable();
            $table->timestamps();

            $table->index(['anio', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebr_ejercicios');
    }
};
