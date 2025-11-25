<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tbcontroloficios', function (Blueprint $table) {
            $table->integer('IDRegistro')->primary(); // No autoincremental
            $table->integer('IDListaN')->nullable();
            $table->text('PathArchivo')->nullable();
            $table->string('Archivo', 255)->nullable();
            $table->integer('IDAccion');
            $table->dateTime('TimeStampArchivo')->default(DB::raw('CURRENT_TIMESTAMP'));

            $table->timestamps(); // created_at / updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tbcontroloficios');
    }
};
