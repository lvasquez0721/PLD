<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $fechaActual = Carbon::now();

        DB::table('catsistemas')->insert([
            [
                'IDSistema' => 1,
                'Sistema' => 'SIT',
                'Activo' => 1,
                'created_at' => $fechaActual,
                'updated_at' => $fechaActual
            ],
            [
                'IDSistema' => 2,
                'Sistema' => 'Xpertys',
                'Activo' => 1,
                'created_at' => $fechaActual,
                'updated_at' => $fechaActual
            ],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('catsistemas')->whereIn('IDSistema', [1, 2])->delete();
    }
};
