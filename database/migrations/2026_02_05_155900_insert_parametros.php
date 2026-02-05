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
        $parametros = [
            [
                'IDParametro' => 1,
                'Parametro' => 'Operaciones relevantes',
                'Valor' => '7500',
                'TipoDato' => 'number',
                'Activo' => 1,
                'TimeStampAlta' => Carbon::parse('2021-03-22 15:46:40.203'),
                'TimeStampModificacion' => Carbon::parse('2021-03-22 15:46:40.217'),
            ],
            [
                'IDParametro' => 2,
                'Parametro' => 'Reporteador Monto Acumulado',
                'Valor' => '75000',
                'TipoDato' => 'number',
                'Activo' => 1,
                'TimeStampAlta' => Carbon::parse('2021-03-22 15:46:40.203'),
                'TimeStampModificacion' => Carbon::parse('2021-03-22 15:46:40.217'),
            ],
            [
                'IDParametro' => 3,
                'Parametro' => 'Porcentaje Match Buscador UIF',
                'Valor' => '60',
                'TipoDato' => 'number',
                'Activo' => 1,
                'TimeStampAlta' => Carbon::parse('2021-03-22 15:46:40.203'),
                'TimeStampModificacion' => Carbon::parse('2021-03-22 15:46:40.203'),
            ],
            [
                'IDParametro' => 4,
                'Parametro' => 'Porcentaje Match Buscador CNSF',
                'Valor' => '75',
                'TipoDato' => 'number',
                'Activo' => 1,
                'TimeStampAlta' => Carbon::parse('2021-03-22 15:46:40.203'),
                'TimeStampModificacion' => Carbon::parse('2021-03-22 15:46:40.203'),
            ],
            [
                'IDParametro' => 5,
                'Parametro' => 'Riesgo Alto Perfil',
                'Valor' => '2.01',
                'TipoDato' => 'number',
                'Activo' => 1,
                'TimeStampAlta' => Carbon::parse('2021-03-22 15:46:40.203'),
                'TimeStampModificacion' => Carbon::parse('2021-05-19 14:55:25.140'),
            ],
            [
                'IDParametro' => 8,
                'Parametro' => 'Desviacion Estandar Inusualidad',
                'Valor' => '3.00',
                'TipoDato' => 'number',
                'Activo' => 1,
                'TimeStampAlta' => Carbon::parse('2022-05-25 08:34:48.523'),
                'TimeStampModificacion' => Carbon::parse('2022-06-28 13:20:09.533'),
            ],
            [
                'IDParametro' => 9,
                'Parametro' => 'Anios considerados Inusualidad',
                'Valor' => '5.00',
                'TipoDato' => 'number',
                'Activo' => 1,
                'TimeStampAlta' => Carbon::parse('2022-05-25 08:34:48.523'),
                'TimeStampModificacion' => Carbon::parse('2022-05-25 08:34:48.523'),
            ],
            [
                'IDParametro' => 14,
                'Parametro' => 'Monto minimo alerta',
                'Valor' => '7500',
                'TipoDato' => 'number',
                'Activo' => 1,
                'TimeStampAlta' => Carbon::parse('2022-12-06 08:49:06.213'),
                'TimeStampModificacion' => Carbon::parse('2024-09-04 15:42:29.693'),
            ],
            [
                'IDParametro' => 15,
                'Parametro' => 'Tolerancia Porcentaje Pagos Fraccionados',
                'Valor' => '10',
                'TipoDato' => 'number',
                'Activo' => 1,
                'TimeStampAlta' => Carbon::parse('2022-12-06 08:49:06.213'),
                'TimeStampModificacion' => Carbon::parse('2022-12-06 08:49:06.213'),
            ],
            [
                'IDParametro' => 16,
                'Parametro' => 'Monto Autorizacion Pago Efectivo PF',
                'Valor' => '300000',
                'TipoDato' => 'number',
                'Activo' => 1,
                'TimeStampAlta' => Carbon::parse('2022-12-06 08:49:06.217'),
                'TimeStampModificacion' => Carbon::parse('2022-12-06 08:49:06.217'),
            ],
            [
                'IDParametro' => 17,
                'Parametro' => 'Monto Autorizacion Pago Efectivo PM',
                'Valor' => '500000',
                'TipoDato' => 'number',
                'Activo' => 1,
                'TimeStampAlta' => Carbon::parse('2022-12-06 08:49:06.217'),
                'TimeStampModificacion' => Carbon::parse('2022-12-06 08:49:06.217'),
            ],
            [
                'IDParametro' => 26,
                'Parametro' => 'urlAuthQeQ',
                'Valor' => 'https://app.q-detect.com/api/token',
                'TipoDato' => 'text',
                'Activo' => 1,
                'TimeStampAlta' => Carbon::parse('2024-11-19 08:21:52.407'),
                'TimeStampModificacion' => Carbon::parse('2024-11-19 08:21:52.407'),
            ],
            [
                'IDParametro' => 27,
                'Parametro' => 'urlQryQeQ',
                'Valor' => 'https://app.q-detect.com/api/find',
                'TipoDato' => 'text',
                'Activo' => 1,
                'TimeStampAlta' => Carbon::parse('2024-11-19 08:21:52.407'),
                'TimeStampModificacion' => Carbon::parse('2024-11-19 08:21:52.407'),
            ],
            [
                'IDParametro' => 28,
                'Parametro' => 'clientIDQeQ',
                'Valor' => '780413-5348-8120',
                'TipoDato' => 'text',
                'Activo' => 1,
                'TimeStampAlta' => Carbon::parse('2024-11-19 08:21:52.407'),
                'TimeStampModificacion' => Carbon::parse('2024-11-19 08:21:52.407'),
            ],
            [
                'IDParametro' => 29,
                'Parametro' => 'usernameQeQ',
                'Valor' => 'Tlaloc_01',
                'TipoDato' => 'text',
                'Activo' => 1,
                'TimeStampAlta' => Carbon::parse('2024-11-19 08:21:52.407'),
                'TimeStampModificacion' => Carbon::parse('2024-11-19 08:21:52.407'),
            ],
            [
                'IDParametro' => 30,
                'Parametro' => 'tokenAPIQeQ',
                'Valor' => 'YKsaMDz7m1F9sVIf6siDavc4HKVo0dpkJci1OIUSKOFAqmxKEltlqQNwcslp0GM9FmS3e0PY16rMJwxPNJB00FukGV2VkkEdnYuKejtiHpONFdJlncX2mmyBIRCTtKko',
                'TipoDato' => 'text',
                'Activo' => 1,
                'TimeStampAlta' => Carbon::parse('2024-11-19 08:21:52.407'),
                'TimeStampModificacion' => Carbon::parse('2024-11-19 08:21:52.407'),
            ],
            [
                'IDParametro' => 31,
                'Parametro' => 'contPFQeQ',
                'Valor' => '0',
                'TipoDato' => 'number',
                'Activo' => 1,
                'TimeStampAlta' => Carbon::parse('2024-11-19 08:21:52.407'),
                'TimeStampModificacion' => Carbon::parse('2024-11-19 08:21:52.407'),
            ],
            [
                'IDParametro' => 32,
                'Parametro' => 'contPMQeQ',
                'Valor' => '1',
                'TipoDato' => 'number',
                'Activo' => 1,
                'TimeStampAlta' => Carbon::parse('2024-11-19 08:21:52.407'),
                'TimeStampModificacion' => Carbon::parse('2024-11-19 08:21:52.407'),
            ],
            [
                'IDParametro' => 33,
                'Parametro' => 'percentMatchQeQ',
                'Valor' => '80',
                'TipoDato' => 'number',
                'Activo' => 1,
                'TimeStampAlta' => Carbon::parse('2024-11-19 08:21:52.407'),
                'TimeStampModificacion' => Carbon::parse('2024-11-19 08:21:52.407'),
            ],
        ];

        foreach ($parametros as $parametro) {
            $exists = DB::table('catParametriaPLD')
                ->where('IDParametro', $parametro['IDParametro'])
                ->exists();

            if (!$exists) {
                DB::table('catParametriaPLD')->insert($parametro);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('catParametriaPLD')->whereIn('IDParametro', [
            1, 2, 3, 4, 5, 8, 9, 14, 15, 16, 17,
            26, 27, 28, 29, 30, 31, 32, 33
        ])->delete();
    }
};
