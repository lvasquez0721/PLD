<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends \Illuminate\Database\Migrations\Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Drop the foreign key constraint if it exists (suppress errors for safe migration)
        try {
            Schema::table('tbOperaciones', function (Blueprint $table) {
                $table->dropForeign(['IDMoneda']);
            });
        } catch (\Exception $e) {
            // Might not exist, safe to ignore
        }

        // 2. Update data to new codes as string, casting condition as string for MySQL safety
        DB::table('tbOperaciones')->where('IDMoneda', '=', '1')->update(['IDMoneda' => 'MXN']);
        DB::table('tbOperaciones')->where('IDMoneda', '=', '2')->update(['IDMoneda' => 'USD']);

        // 3. Change the type of IDMoneda to string (nullable)
        Schema::table('tbOperaciones', function (Blueprint $table) {
            $table->string('IDMoneda')->nullable()->change();
        });

        // 4. (Optional) Foreign key recreation must match new type and referenced PK type, so leave commented out until confirmed necessary
        /*
        Schema::table('tbOperaciones', function (Blueprint $table) {
            $table->foreign('IDMoneda')->references('IDMoneda')->on('tbMonedas');
        });
        */
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // 1. Drop foreign key for string key, if any (ignore failure)
        try {
            Schema::table('tbOperaciones', function (Blueprint $table) {
                $table->dropForeign(['IDMoneda']);
            });
        } catch (\Exception $e) {
            // ignore if doesn't exist
        }

        // 2. Update string codes back to numbers prior to column type revert
        DB::table('tbOperaciones')->where('IDMoneda', 'MXN')->update(['IDMoneda' => '1']);
        DB::table('tbOperaciones')->where('IDMoneda', 'USD')->update(['IDMoneda' => '2']);

        // 3. Change the type of IDMoneda back to integer (nullable)
        Schema::table('tbOperaciones', function (Blueprint $table) {
            $table->integer('IDMoneda')->nullable()->change();
        });

        // 4. (Optional) Recreate original foreign key constraint for integer if desired
        /*
        Schema::table('tbOperaciones', function (Blueprint $table) {
            $table->foreign('IDMoneda')->references('IDMoneda')->on('tbMonedas');
        });
        */
    }
};
