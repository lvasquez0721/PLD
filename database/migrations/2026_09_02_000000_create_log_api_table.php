<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('logApi', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('IDUsuario')->nullable();
            $table->string('Usuario', 100)->nullable();

            $table->string('Metodo', 10);
            $table->string('Ruta')->nullable();
            $table->text('URL')->nullable();

            $table->string('IP', 45)->nullable();
            $table->text('UserAgent')->nullable();

            $table->integer('Estatus')->nullable();
            $table->decimal('DuracionMs', 10, 2)->nullable();

            $table->longText('Payload')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('logApi');
    }
};
