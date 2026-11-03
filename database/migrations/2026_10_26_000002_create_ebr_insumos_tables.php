<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ebr_polizas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ejercicio_id')->constrained('ebr_ejercicios')->cascadeOnDelete();
            $table->string('no_poliza', 80);
            $table->string('no_cliente', 80)->nullable();
            $table->string('nombre_cliente', 255)->nullable();
            $table->enum('tipo_persona', ['Persona Física', 'Persona Moral'])->default('Persona Física');
            $table->string('rfc', 20)->nullable();
            $table->string('curp', 30)->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->date('fecha_constitucion')->nullable();
            $table->date('fecha_primera_relacion')->nullable();
            $table->decimal('edad', 5, 2)->nullable();
            $table->decimal('antiguedad_empresa_anios', 6, 2)->nullable();
            $table->decimal('antiguedad_relacion_anios', 6, 2)->nullable();
            $table->string('ocupacion_giro', 255)->nullable();
            $table->unsignedTinyInteger('actividad_nivel_riesgo')->nullable();
            $table->string('actividad_agrupada', 120)->nullable();
            $table->string('estado', 120)->nullable();
            $table->string('pais_nacionalidad', 120)->default('México');
            $table->string('pais_operacion', 120)->default('México');
            $table->string('categoria_pld', 120)->nullable();
            $table->unsignedTinyInteger('lista_riesgo')->nullable();
            $table->string('canal', 40)->nullable();
            $table->string('agente', 255)->nullable();
            $table->string('ramo', 120)->nullable();
            $table->string('producto', 255)->nullable();
            $table->string('grupo_producto', 40)->nullable();
            $table->decimal('prima_total', 18, 2)->default(0);
            $table->string('moneda', 10)->default('MXN');
            $table->decimal('tipo_cambio', 14, 6)->default(1);
            $table->date('fecha_emision')->nullable();
            $table->unsignedInteger('num_operaciones_entrada')->default(1);
            $table->boolean('excluida')->default(false);
            $table->string('motivo_exclusion', 255)->nullable();
            $table->string('row_hash', 64)->nullable();
            $table->timestamps();

            $table->unique(['ejercicio_id', 'no_poliza', 'no_cliente'], 'ebr_polizas_unica');
            $table->index(['ejercicio_id', 'no_poliza']);
            $table->index(['ejercicio_id', 'no_cliente']);
            $table->index(['ejercicio_id', 'grupo_producto']);
            $table->index(['ejercicio_id', 'estado']);
            $table->index(['ejercicio_id', 'canal']);
        });

        Schema::create('ebr_siniestros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ejercicio_id')->constrained('ebr_ejercicios')->cascadeOnDelete();
            $table->string('no_siniestro', 80);
            $table->string('no_poliza', 80);
            $table->string('no_cliente', 80)->nullable();
            $table->date('fecha_pago');
            $table->decimal('monto_pagado', 18, 2)->default(0);
            $table->string('moneda', 10)->default('MXN');
            $table->decimal('tipo_cambio', 14, 6)->default(1);
            $table->unsignedInteger('num_operaciones_salida')->default(1);
            $table->string('estado', 120)->nullable();
            $table->string('producto', 255)->nullable();
            $table->string('grupo_producto', 40)->nullable();
            $table->string('canal', 40)->nullable();
            $table->string('row_hash', 64)->nullable();
            $table->timestamps();

            $table->unique(['ejercicio_id', 'no_siniestro'], 'ebr_siniestros_unico');
            $table->index(['ejercicio_id', 'no_poliza']);
            $table->index(['ejercicio_id', 'estado']);
        });

        Schema::create('ebr_alertas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ejercicio_id')->constrained('ebr_ejercicios')->cascadeOnDelete();
            $table->string('patron', 80)->nullable();
            $table->string('no_cliente', 80)->nullable();
            $table->string('no_poliza', 80)->nullable();
            $table->date('fecha_deteccion');
            $table->decimal('monto', 18, 2)->default(0);
            $table->string('instrumento_monetario', 80)->nullable();
            $table->string('agente', 255)->nullable();
            $table->string('estatus', 80)->nullable();
            $table->boolean('procedio')->default(false);
            $table->unsignedInteger('num_operaciones')->default(1);
            $table->text('descripcion')->nullable();
            $table->text('razones')->nullable();
            $table->string('row_hash', 64)->nullable();
            $table->timestamps();

            $table->index(['ejercicio_id', 'patron']);
            $table->index(['ejercicio_id', 'procedio']);
        });

        Schema::create('ebr_importaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ejercicio_id')->constrained('ebr_ejercicios')->cascadeOnDelete();
            $table->enum('tipo', ['clientes', 'siniestros', 'alertas']);
            $table->string('nombre_archivo', 255);
            $table->string('ruta', 500)->nullable();
            $table->string('hash', 64)->nullable();
            $table->unsignedInteger('total_filas')->default(0);
            $table->unsignedInteger('filas_validas')->default(0);
            $table->unsignedInteger('filas_error')->default(0);
            $table->json('errores')->nullable();
            $table->json('resumen')->nullable();
            $table->enum('estado', ['prevalidado', 'importado', 'error'])->default('prevalidado');
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['ejercicio_id', 'tipo', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebr_importaciones');
        Schema::dropIfExists('ebr_alertas');
        Schema::dropIfExists('ebr_siniestros');
        Schema::dropIfExists('ebr_polizas');
    }
};
