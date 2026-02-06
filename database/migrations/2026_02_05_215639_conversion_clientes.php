<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tbClientes', function (Blueprint $table) {
            $table->string('IDNacionalidad')->nullable()->change();
        });

        DB::table('tbClientes')->where('IDNacionalidad', 1)->update(['IDNacionalidad' => 'MX']);
        DB::table('tbClientes')->where('IDNacionalidad', '!=', 'MX')->update(['IDNacionalidad' => null]);
    }

    public function down(): void
    {
        DB::table('tbClientes')->where('IDNacionalidad', 'MX')->update(['IDNacionalidad' => 1]);
        DB::table('tbClientes')->whereNull('IDNacionalidad')->update(['IDNacionalidad' => 0]);

        Schema::table('tbClientes', function (Blueprint $table) {
            $table->string('IDNacionalidad')->nullable(false)->change();
        });
    }
};
