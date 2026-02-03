<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Migration to change catNacionalidad.IDNacionalidad from integer to string,
 * and update logClientes.IDNacionalidad to keep it as column but drop its FK constraint,
 * since catNacionalidad.IDNacionalidad is now a string PK.
 *
 * Solves error: "Cannot drop index 'PRIMARY': needed in a foreign key constraint"
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Drop FK from logClientes to catNacionalidad *before* dropping PK/index on catNacionalidad

        // We'll try to identify the FK name reliably with information_schema query (in case the name is different)
        $logClientesTable = 'logClientes';
        $catNacionalidadTable = 'catNacionalidad';

        $fkName = DB::table('INFORMATION_SCHEMA.KEY_COLUMN_USAGE')
            ->where('TABLE_NAME', $logClientesTable)
            ->where('COLUMN_NAME', 'IDNacionalidad')
            ->where('REFERENCED_TABLE_NAME', $catNacionalidadTable)
            ->where('REFERENCED_COLUMN_NAME', 'IDNacionalidad')
            ->where('TABLE_SCHEMA', env('DB_DATABASE'))
            ->value('CONSTRAINT_NAME');

        if ($fkName) {
            Schema::table($logClientesTable, function (Blueprint $table) use ($fkName) {
                $table->dropForeign($fkName);
            });
        } else {
            // Backup in case INFORMATION_SCHEMA fails, try convention name
            Schema::table($logClientesTable, function (Blueprint $table) {
                try {
                    $table->dropForeign(['IDNacionalidad']);
                } catch (\Throwable $e) {}
            });
        }

        // 2. Drop PRIMARY KEY constraint on catNacionalidad
        Schema::table('catNacionalidad', function (Blueprint $table) {
            $table->dropPrimary();
        });

        // 3. Change datatype to string
        Schema::table('catNacionalidad', function (Blueprint $table) {
            $table->string('IDNacionalidad')->change();
        });

        // 4. Re-add PRIMARY KEY constraint as string
        Schema::table('catNacionalidad', function (Blueprint $table) {
            $table->primary('IDNacionalidad');
        });

        // 5. Ensure logClientes.IDNacionalidad is string, but does not have FK
        Schema::table('logClientes', function (Blueprint $table) {
            $table->string('IDNacionalidad')->nullable()->change();
            // The column stays, no FK is restored here
        });
    }

    public function down(): void
    {
        // 1. Drop PRIMARY KEY constraint on catNacionalidad (string)
        Schema::table('catNacionalidad', function (Blueprint $table) {
            $table->dropPrimary();
        });

        // 2. Change column back to unsignedBigInteger
        Schema::table('catNacionalidad', function (Blueprint $table) {
            $table->unsignedBigInteger('IDNacionalidad')->change();
        });

        // 3. Re-add PRIMARY KEY constraint (int)
        Schema::table('catNacionalidad', function (Blueprint $table) {
            $table->primary('IDNacionalidad');
        });

        // 4. Change logClientes.IDNacionalidad field back to unsignedBigInteger
        Schema::table('logClientes', function (Blueprint $table) {
            $table->unsignedBigInteger('IDNacionalidad')->nullable()->change();
        });

        // 5. Restore FK from logClientes.IDNacionalidad -> catNacionalidad.IDNacionalidad
        Schema::table('logClientes', function (Blueprint $table) {
            $table->foreign('IDNacionalidad')->references('IDNacionalidad')->on('catNacionalidad')->onDelete('set null');
        });
    }
};
