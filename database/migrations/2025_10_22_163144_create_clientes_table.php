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
        Schema::create('clientes', function (Blueprint $table) {
            $table->bigIncrements('id'); // Solo puede haber un auto-increment y PK

            // Si quieres mantener IDSslicitante como campo único, NO puede ser auto-increment ni PK
            $table->unsignedBigInteger('IDSolicitante')->nullable();

            $table->string('no_cliente')->unique();
            $table->string('nombre');
            $table->string('apellido_p');
            $table->string('apellido_m')->nullable();
            $table->enum('tipo_persona', ['FISICA', 'MORAL']);
            $table->string('curp', 18)->nullable();
            $table->string('rfc', 13)->nullable();
            $table->string('ocupacion_giro')->nullable();
            $table->string('estado_radica')->nullable();
            $table->date('fecha_nacimiento')->nullable();
            $table->unsignedTinyInteger('edad')->nullable();
            $table->string('calle')->nullable();
            $table->string('no_exterior', 16)->nullable();
            $table->string('colonia')->nullable();
            $table->string('cp', 8)->nullable();
            $table->unsignedBigInteger('id_estado')->nullable();
            $table->unsignedBigInteger('id_municipio')->nullable();
            $table->unsignedBigInteger('id_localidad')->nullable();
            $table->timestamps();

            // Indexes for faster lookup (optional, but recommended)
            $table->index('no_cliente');
            $table->index('rfc');
            $table->index('curp');

            // Opcional: Si IDSslicitante debe ser único:
            // $table->unique('IDSolicitante');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};
