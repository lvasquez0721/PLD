<?php

use App\Services\PLD\DeduplicadorAlertasCliente;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tbAlertas', 'ActividadVulnerableUnica')) {
            Schema::table('tbAlertas', function (Blueprint $table) {
                $table->string('ActividadVulnerableUnica', 64)
                    ->nullable()
                    ->after('Patron');
            });
        }

        // Deduplica por identidad (RFC/CURP/nombre) y rellena la clave de
        // unicidad de todas las alertas de Actividad vulnerable.
        app(DeduplicadorAlertasCliente::class)->deduplicar('Actividad vulnerable', false);

        $existeIndice = collect(DB::select(
            'SHOW INDEX FROM tbAlertas WHERE Key_name = ?',
            ['uniq_tb_alertas_actividad_vulnerable']
        ))->isNotEmpty();

        if (! $existeIndice) {
            DB::statement(
                'CREATE UNIQUE INDEX uniq_tb_alertas_actividad_vulnerable '
                .'ON tbAlertas (ActividadVulnerableUnica)'
            );
        }
    }

    public function down(): void
    {
        $existeIndice = collect(DB::select(
            'SHOW INDEX FROM tbAlertas WHERE Key_name = ?',
            ['uniq_tb_alertas_actividad_vulnerable']
        ))->isNotEmpty();

        if ($existeIndice) {
            DB::statement('DROP INDEX uniq_tb_alertas_actividad_vulnerable ON tbAlertas');
        }

        if (Schema::hasColumn('tbAlertas', 'ActividadVulnerableUnica')) {
            Schema::table('tbAlertas', function (Blueprint $table) {
                $table->dropColumn('ActividadVulnerableUnica');
            });
        }
    }
};
