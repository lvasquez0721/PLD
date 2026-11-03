<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personas_importaciones', function (Blueprint $table) {
            $table->id();
            $table->string('tipo_persona', 20)->index();
            $table->string('nombre_archivo');
            $table->string('ruta');
            $table->string('hash', 64)->nullable();
            $table->unsignedInteger('total_filas')->default(0);
            $table->unsignedInteger('filas_validas')->default(0);
            $table->unsignedInteger('filas_error')->default(0);
            $table->json('errores')->nullable();
            $table->json('resumen')->nullable();
            $table->string('estado', 20)->default('prevalidado')->index();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personas_importaciones');
    }
};
