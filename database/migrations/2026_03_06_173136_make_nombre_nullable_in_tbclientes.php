<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbClientes', function (Blueprint $table) {
            $table->string('Nombre')->nullable()->change();
            $table->string('ApellidoPaterno')->nullable()->change();
            $table->string('ApellidoMaterno')->nullable()->change();
            $table->string('CURP')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('tbClientes', function (Blueprint $table) {
            $table->string('Nombre')->nullable(false)->change();
            $table->string('ApellidoPaterno')->nullable(false)->change();
            $table->string('ApellidoMaterno')->nullable(false)->change();
            $table->string('CURP')->nullable(false)->change();
        });
    }
};
