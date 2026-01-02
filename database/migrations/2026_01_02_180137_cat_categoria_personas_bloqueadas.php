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
        //
        Schema::create('cat_categoria_personas_bloqueadas', function (Blueprint $table) {
            $table->increments('ID');

            // Campos
            $table->integer('IDCategoria')->unique();
            $table->string('Categoria', 128)->default('');
            $table->string('Color', 40)->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
