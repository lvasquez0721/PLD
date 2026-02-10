<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $fechaActual = Carbon::now();

        $sistemas = [
            [
                'IDSistema' => 1,
                'Sistema' => 'SIT',
                'Activo' => 1,
                'created_at' => $fechaActual,
                'updated_at' => $fechaActual,
            ],
            [
                'IDSistema' => 2,
                'Sistema' => 'Xpertys',
                'Activo' => 1,
                'created_at' => $fechaActual,
                'updated_at' => $fechaActual,
            ],
        ];

        foreach ($sistemas as $sistema) {
            $existe = DB::table('catSistemas')->where('IDSistema', $sistema['IDSistema'])->exists();
            if (! $existe) {
                DB::table('catSistemas')->insert($sistema);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('catsistemas')->whereIn('IDSistema', [1, 2])->delete();
    }
};
