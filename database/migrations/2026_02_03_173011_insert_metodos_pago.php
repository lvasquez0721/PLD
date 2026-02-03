<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends \Illuminate\Database\Migrations\Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $metodos_pago = [
            ['IDFormaPago' => '01', 'FormaPago' => 'Efectivo'],
            ['IDFormaPago' => '02', 'FormaPago' => 'Cheque nominativo'],
            ['IDFormaPago' => '03', 'FormaPago' => 'Transferencia electrónica de fondos'],
            ['IDFormaPago' => '04', 'FormaPago' => 'Tarjeta de crédito'],
            ['IDFormaPago' => '05', 'FormaPago' => 'Monedero electrónico'],
            ['IDFormaPago' => '06', 'FormaPago' => 'Dinero electrónico'],
            ['IDFormaPago' => '08', 'FormaPago' => 'Vales de despensa'],
            ['IDFormaPago' => '12', 'FormaPago' => 'Dación en pago'],
            ['IDFormaPago' => '13', 'FormaPago' => 'Pago por subrogación'],
            ['IDFormaPago' => '14', 'FormaPago' => 'Pago por consignación'],
            ['IDFormaPago' => '15', 'FormaPago' => 'Condonación'],
            ['IDFormaPago' => '17', 'FormaPago' => 'Compensación'],
            ['IDFormaPago' => '23', 'FormaPago' => 'Novación'],
            ['IDFormaPago' => '24', 'FormaPago' => 'Confusión'],
            ['IDFormaPago' => '25', 'FormaPago' => 'Remisión de deuda'],
            ['IDFormaPago' => '26', 'FormaPago' => 'Prescripción o caducidad'],
            ['IDFormaPago' => '27', 'FormaPago' => 'A satisfacción del acreedor'],
            ['IDFormaPago' => '28', 'FormaPago' => 'Tarjeta de débito'],
            ['IDFormaPago' => '29', 'FormaPago' => 'Tarjeta de servicios'],
            ['IDFormaPago' => '30', 'FormaPago' => 'Aplicación de anticipos'],
            ['IDFormaPago' => '31', 'FormaPago' => 'Intermediario pagos'],
            ['IDFormaPago' => '99', 'FormaPago' => 'Por definir'],
        ];

        foreach ($metodos_pago as $metodo) {
            DB::table('catFormaPagos')->updateOrInsert(
                ['IDFormaPago' => $metodo['IDFormaPago']],
                $metodo
            );
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $ids = [
            '01','02','03','04','05','06','08','12','13','14','15','17',
            '23','24','25','26','27','28','29','30','31','99'
        ];
        DB::table('catFormaPagos')->whereIn('IDFormaPago', $ids)->delete();
    }
};
