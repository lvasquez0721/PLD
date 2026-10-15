<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logCorreos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('Destinatario', 255)->nullable();
            $table->string('NombreDestinatario', 255)->nullable();
            $table->string('Asunto', 500)->nullable();
            $table->string('Tipo', 50);
            $table->unsignedBigInteger('IDCliente')->nullable();
            $table->unsignedBigInteger('IDOperacion')->nullable();
            $table->text('Patrones')->nullable();
            $table->integer('AlertasCount')->default(0);
            $table->longText('CuerpoHtml')->nullable();
            $table->boolean('Truncado')->default(false);
            $table->string('Estado', 30)->default('Enviado');
            $table->text('Error')->nullable();
            $table->timestamps();
            $table->index('Tipo');
            $table->index('Estado');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logCorreos');
    }
};
