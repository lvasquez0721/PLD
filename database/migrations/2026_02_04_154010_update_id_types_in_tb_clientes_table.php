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
        Schema::table('tbClientes', function (Blueprint $table) {
            // Change IDNacionalidad to string
            $table->string('IDNacionalidad', 10)->change();

            // Change IDEstadoNacimiento to string
            $table->string('IDEstadoNacimiento', 10)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tbClientes', function (Blueprint $table) {
            // Revert IDNacionalidad to unsignedBigInteger
            $table->unsignedBigInteger('IDNacionalidad')->change();

            // Revert IDEstadoNacimiento to unsignedBigInteger
            $table->unsignedBigInteger('IDEstadoNacimiento')->nullable()->change();
        });
    }
};
