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
        Schema::table('users', function (Blueprint $table) {
            $table->string('nombre')->after('id');
            $table->string('apellido_p')->after('nombre');
            $table->string('apellido_m')->after('apellido_p');
            $table->boolean('primer_login')->default(true)->after('apellido_m');
            $table->dropColumn('name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['nombre', 'apellido_p', 'apellido_m', 'primer_login']);
            $table->string('name')->after('id');
        });
    }
};
