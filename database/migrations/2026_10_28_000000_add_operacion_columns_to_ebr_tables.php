<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ebr_polizas', function (Blueprint $table) {
            $table->string('id_operacion', 80)->nullable()->after('no_poliza');
            $table->string('folio_endoso', 80)->nullable()->after('id_operacion');
            $table->string('tipo_movimiento', 40)->nullable()->after('folio_endoso');
            $table->string('clave_operacion', 64)->nullable()->after('row_hash');
        });

        Schema::table('ebr_polizas', function (Blueprint $table) {
            $table->dropUnique('ebr_polizas_unica');
            $table->unique(['ejercicio_id', 'clave_operacion'], 'ebr_polizas_operacion_unica');
            $table->index(['ejercicio_id', 'id_operacion'], 'ebr_polizas_id_operacion_index');
        });

        DB::table('ebr_polizas')->orderBy('id')->chunkById(500, function ($polizas) {
            foreach ($polizas as $poliza) {
                DB::table('ebr_polizas')->where('id', $poliza->id)->update([
                    'clave_operacion' => hash('sha256', implode('|', [
                        $poliza->no_poliza,
                        $poliza->no_cliente,
                        $poliza->folio_endoso ?? '',
                        $poliza->id_operacion ?? '',
                        $poliza->fecha_emision ?? '',
                    ])),
                ]);
            }
        });

        Schema::table('ebr_alertas', function (Blueprint $table) {
            $table->string('id_operacion', 80)->nullable()->after('no_poliza');
            $table->string('moneda', 10)->default('MXN')->after('monto');
            $table->decimal('tipo_cambio', 14, 6)->default(1)->after('moneda');
            $table->index(['ejercicio_id', 'id_operacion'], 'ebr_alertas_id_operacion_index');
        });
    }

    public function down(): void
    {
        Schema::table('ebr_alertas', function (Blueprint $table) {
            $table->dropIndex('ebr_alertas_id_operacion_index');
            $table->dropColumn(['id_operacion', 'moneda', 'tipo_cambio']);
        });

        Schema::table('ebr_polizas', function (Blueprint $table) {
            $table->dropIndex('ebr_polizas_id_operacion_index');
            $table->dropUnique('ebr_polizas_operacion_unica');
            $table->unique(['ejercicio_id', 'no_poliza', 'no_cliente'], 'ebr_polizas_unica');
            $table->dropColumn(['id_operacion', 'folio_endoso', 'tipo_movimiento', 'clave_operacion']);
        });
    }
};
