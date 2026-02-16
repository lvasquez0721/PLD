<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Insert catalog entries into catTipoOperacion
        DB::table('catTipoOperacion')->insert([
            ['IDTipoOperacion' => 1, 'TipoOperacion' => 'DEPOSITO'],
            ['IDTipoOperacion' => 2, 'TipoOperacion' => 'RETIRO'],
            ['IDTipoOperacion' => 3, 'TipoOperacion' => 'COMPRA DIVISAS'],
            ['IDTipoOperacion' => 4, 'TipoOperacion' => 'VENTA DIVISAS'],
            ['IDTipoOperacion' => 5, 'TipoOperacion' => 'CHEQUES DE CAJA'],
            ['IDTipoOperacion' => 6, 'TipoOperacion' => 'GIROS'],
            ['IDTipoOperacion' => 7, 'TipoOperacion' => 'ORDENES DE PAGO'],
            ['IDTipoOperacion' => 8, 'TipoOperacion' => 'OTORGAMIENTO DE CREDITO'],
            ['IDTipoOperacion' => 9, 'TipoOperacion' => 'PAGO DE CREDITO'],
            ['IDTipoOperacion' => 10, 'TipoOperacion' => 'PAGO DE PRIMAS U OPERACION DE REASEGURO'],
            ['IDTipoOperacion' => 11, 'TipoOperacion' => 'APORTACIONES A UN CONTRATO/FIDEICOMISO'],
            ['IDTipoOperacion' => 12, 'TipoOperacion' => 'DEPOSITOS EN GARANTIA'],
            ['IDTipoOperacion' => 13, 'TipoOperacion' => 'SALVAMENTOS'],
            ['IDTipoOperacion' => 14, 'TipoOperacion' => 'DEPOSITO PATRONAL'],
            ['IDTipoOperacion' => 15, 'TipoOperacion' => 'DEPOSITO DE TRABAJADOR'],
            ['IDTipoOperacion' => 16, 'TipoOperacion' => 'PRESTAMOS O CREDITOS PARA LA ADQUISICION DE VALORES CON GARANTIA'],
            ['IDTipoOperacion' => 17, 'TipoOperacion' => 'REPORTOS Y PRESTAMOS SOBRE VALORES'],
            ['IDTipoOperacion' => 18, 'TipoOperacion' => 'COMPRA DE VALORES'],
            ['IDTipoOperacion' => 19, 'TipoOperacion' => 'VENTA DE VALORES'],
            ['IDTipoOperacion' => 20, 'TipoOperacion' => 'DEPOSITO DE APORTACION VOLUNTARIA O COMPLEMENTARIA'],
            ['IDTipoOperacion' => 21, 'TipoOperacion' => 'DISPOSICION DE APORTACION VOLUNTARIA O COMPLEMENTARIA'],
            ['IDTipoOperacion' => 22, 'TipoOperacion' => 'ALMACENAMIENTO DE BIENES O MERCANCIAS'],
            ['IDTipoOperacion' => 23, 'TipoOperacion' => 'EMPAQUE Y ENVASADO DE BIENES Y MERCANCIAS'],
            ['IDTipoOperacion' => 24, 'TipoOperacion' => 'GESTION DE GARANTIAS'],
            ['IDTipoOperacion' => 25, 'TipoOperacion' => 'SERVICIOS DE DEPOSITO FISCAL'],
            ['IDTipoOperacion' => 26, 'TipoOperacion' => 'CONTRATACION DE ARRENDAMIENTO FINANCIERO'],
            ['IDTipoOperacion' => 27, 'TipoOperacion' => 'PAGO DE RENTAS DE ARRENDAMIENTO FINANCIERO'],
            ['IDTipoOperacion' => 28, 'TipoOperacion' => 'VENTA DE BIENES ARRENDADOS'],
            ['IDTipoOperacion' => 29, 'TipoOperacion' => 'ADQUISICION DE BIENES DEL FUTURO ARRENDATARIO'],
            ['IDTipoOperacion' => 30, 'TipoOperacion' => 'RECEPCION DE PRESTAMOS'],
            ['IDTipoOperacion' => 31, 'TipoOperacion' => 'ADQUISICION DE DOCUMENTOS'],
            ['IDTipoOperacion' => 32, 'TipoOperacion' => 'CONTRATACION, CONSTRUCCION O ADMINISTRACION DE OBRAS'],
            ['IDTipoOperacion' => 33, 'TipoOperacion' => 'PROMOCION DE ORGANIZACION Y ADMINISTRACION DE EMPRESAS'],
            ['IDTipoOperacion' => 34, 'TipoOperacion' => 'COMPRA Y VENTA DE FRUTOS O PRODUCTOS'],
            ['IDTipoOperacion' => 35, 'TipoOperacion' => 'CONTRATOS DE FACTORAJE FINANCIERO'],
            ['IDTipoOperacion' => 36, 'TipoOperacion' => 'SERVICIOS DE ADMINISTRACION Y COBRANZA DE CREDITOS'],
            ['IDTipoOperacion' => 98, 'TipoOperacion' => 'SALIDA DE MAS DE $10MIL DLS (EXCLUSIVO DECLARACION DE ADUANAS)'],
            ['IDTipoOperacion' => 99, 'TipoOperacion' => 'ENTRADA DE MAS DE $10MIL DLS (EXCLUSIVO DECLARACION DE ADUANAS)'],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove the inserted catalog entries using IDs
        DB::table('catTipoOperacion')->whereIn('IDTipoOperacion', [
            1,2,3,4,5,6,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,
            30,31,32,33,34,35,36,98,99
        ])->delete();
    }
};
