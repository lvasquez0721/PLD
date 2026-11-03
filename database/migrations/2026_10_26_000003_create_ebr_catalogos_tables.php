<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ebr_cat_estados', function (Blueprint $table) {
            $table->id();
            $table->string('estado', 120)->unique();
            $table->decimal('valor_paz', 8, 4)->nullable();
            $table->string('nivel_paz', 20)->nullable();
            $table->unsignedTinyInteger('riesgo_ubicacion')->default(3);
            $table->decimal('score_paz', 4, 2)->default(3);
            $table->decimal('score_frontera', 4, 2)->default(1);
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('ebr_cat_productos', function (Blueprint $table) {
            $table->id();
            $table->string('producto', 255)->unique();
            $table->string('ramo', 120)->nullable();
            $table->string('grupo', 40)->default('Otro');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('ebr_cat_actividades', function (Blueprint $table) {
            $table->id();
            $table->string('ocupacion_giro', 255)->unique();
            $table->unsignedTinyInteger('nivel_riesgo')->default(1);
            $table->string('ocupacion_agrupada', 120)->default('No definido');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });

        Schema::create('ebr_cat_paises', function (Blueprint $table) {
            $table->id();
            $table->string('pais', 120)->unique();
            $table->decimal('score_bis', 4, 2)->default(3);
            $table->string('nivel', 20)->default('Medio');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ebr_cat_paises');
        Schema::dropIfExists('ebr_cat_actividades');
        Schema::dropIfExists('ebr_cat_productos');
        Schema::dropIfExists('ebr_cat_estados');
    }
};
