<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        /*
        |--------------------------------------------------------------------------
        | 1. Eliminar FK en tbOperacionesPagos y tbOperaciones sobre IDMoneda
        |--------------------------------------------------------------------------
        */
        // Eliminar FK tbOperacionesPagos.IDMoneda -> catMonedas.IDMoneda
        try {
            Schema::table('tbOperacionesPagos', function (Blueprint $table) {
                $table->dropForeign(['IDMoneda']);
            });
        } catch (\Exception $e) {
        }

        // Eliminar FK tbOperaciones.IDMoneda -> catMonedas.IDMoneda solo si existe
        try {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $doctrineTable = $sm->listTableDetails('tbOperaciones');
            foreach ($doctrineTable->getForeignKeys() as $fk) {
                if (in_array('IDMoneda', $fk->getColumns())) {
                    Schema::table('tbOperaciones', function (Blueprint $table) use ($fk) {
                        $table->dropForeign($fk->getName());
                    });
                    break;
                }
            }
        } catch (\Exception $e) {
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Cambiar tipo de IDMoneda a string en las 3 tablas
        |--------------------------------------------------------------------------
        */
        // Cambiar tbOperacionesPagos.IDMoneda -> string
        Schema::table('tbOperacionesPagos', function (Blueprint $table) {
            $table->string('IDMoneda', 10)->nullable()->change();
        });

        // Cambiar tbOperaciones.IDMoneda -> string
        Schema::table('tbOperaciones', function (Blueprint $table) {
            $table->string('IDMoneda', 10)->nullable()->change();
        });

        // Cambiar catMonedas.IDMoneda -> string y modificar PK
        DB::statement('ALTER TABLE catMonedas DROP PRIMARY KEY');
        Schema::table('catMonedas', function (Blueprint $table) {
            $table->string('IDMoneda', 10)->change();
        });
        DB::statement('ALTER TABLE catMonedas ADD PRIMARY KEY (IDMoneda)');

        /*
        |--------------------------------------------------------------------------
        | 3. Restaurar las FKs para el nuevo tipo de IDMoneda
        |--------------------------------------------------------------------------
        */
        // FK tbOperaciones.IDMoneda -> catMonedas.IDMoneda
        Schema::table('tbOperaciones', function (Blueprint $table) {
            $table->foreign('IDMoneda')
                ->references('IDMoneda')
                ->on('catMonedas')
                ->onDelete('set null');
        });

        // FK tbOperacionesPagos.IDMoneda -> catMonedas.IDMoneda
        Schema::table('tbOperacionesPagos', function (Blueprint $table) {
            $table->foreign('IDMoneda')
                ->references('IDMoneda')
                ->on('catMonedas')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        /*
        |--------------------------------------------------------------------------
        | 1. Quitar las FKs antes de revertir tipo
        |--------------------------------------------------------------------------
        */
        try {
            Schema::table('tbOperacionesPagos', function (Blueprint $table) {
                $table->dropForeign(['IDMoneda']);
            });
        } catch (\Exception $e) {
        }

        try {
            $sm = Schema::getConnection()->getDoctrineSchemaManager();
            $doctrineTable = $sm->listTableDetails('tbOperaciones');
            foreach ($doctrineTable->getForeignKeys() as $fk) {
                if (in_array('IDMoneda', $fk->getColumns())) {
                    Schema::table('tbOperaciones', function (Blueprint $table) use ($fk) {
                        $table->dropForeign($fk->getName());
                    });
                    break;
                }
            }
        } catch (\Exception $e) {
        }

        /*
        |--------------------------------------------------------------------------
        | 2. Revertir tipo de IDMoneda (string a unsignedBigInteger)
        |--------------------------------------------------------------------------
        */
        Schema::table('tbOperacionesPagos', function (Blueprint $table) {
            $table->unsignedBigInteger('IDMoneda')->nullable()->change();
        });
        Schema::table('tbOperaciones', function (Blueprint $table) {
            $table->unsignedBigInteger('IDMoneda')->nullable()->change();
        });

        DB::statement('ALTER TABLE catMonedas DROP PRIMARY KEY');
        Schema::table('catMonedas', function (Blueprint $table) {
            $table->unsignedBigInteger('IDMoneda')->change();
        });
        DB::statement('ALTER TABLE catMonedas ADD PRIMARY KEY (IDMoneda)');

        /*
        |--------------------------------------------------------------------------
        | 3. Restaurar las FKs originales para unsignedBigInteger
        |--------------------------------------------------------------------------
        */
        Schema::table('tbOperaciones', function (Blueprint $table) {
            $table->foreign('IDMoneda')
                ->references('IDMoneda')
                ->on('catMonedas')
                ->onDelete('set null');
        });

        Schema::table('tbOperacionesPagos', function (Blueprint $table) {
            $table->foreign('IDMoneda')
                ->references('IDMoneda')
                ->on('catMonedas')
                ->onDelete('set null');
        });
    }
};
